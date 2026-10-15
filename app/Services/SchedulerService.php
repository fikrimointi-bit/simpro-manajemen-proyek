<?php

namespace App\Services;

use App\Exceptions\SchedulingException;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Mesin penjadwalan dependensi antar tugas (tahap 3A — logika murni).
 *
 * ATURAN (semua perhitungan dalam HARI KERJA memakai CalendarService,
 * tanggal inklusif — hari mulai dan hari selesai sama-sama dihitung):
 *
 * 1. FS (finish-to-start) : mulai di hari kerja SETELAH pendahulu selesai + delay.
 * 2. SS (start-to-start)  : mulai = mulai pendahulu + delay.
 * 3. FF (finish-to-finish): selesai = selesai pendahulu + delay, mulai dihitung mundur.
 * 4. SF (start-to-finish) : selesai = mulai pendahulu + delay, mulai dihitung mundur.
 * 5. Banyak predecessor   : hitung tiap kandidat, ambil yang PALING LAMBAT.
 * 6. Tanpa predecessor    : pakai start_date tugas itu sendiri.
 *
 * CATATAN PENTING:
 * - Dependensi HANYA antar tugas leaf (tanpa sub-tugas). Tugas induk tidak
 *   pernah dijadwalkan lewat dependensi — induk diagregasi dari sub-tugasnya
 *   (Task::recomputeSchedule) SETELAH seluruh leaf selesai dijadwalkan.
 * - Urutan pengerjaan memakai topological sort (algoritma Kahn). Bila ada
 *   siklus, SchedulingException dilempar SEBELUM satu pun tanggal ditulis,
 *   sehingga perubahan tidak pernah tersimpan.
 * - `delay` disimpan di kolom `lag_days`; kolom lama tidak di-rename
 *   (`successor_id` = tugas yang menunggu), sesuai keputusan tahap 3A.
 */
class SchedulerService
{
    public function __construct(private CalendarService $kalender)
    {
    }

    /**
     * Jadwalkan ulang SELURUH proyek.
     *
     * Dipanggil saat tugas berubah (TaskController store/update/destroy),
     * saat dependensi berubah (event model TaskDependency), dan saat
     * kalender proyek berubah (hari libur / pengaturan weekend).
     */
    public function scheduleProject(Project $project): void
    {
        $tasks = $project->tasks()->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $petaTugas = $tasks->keyBy('id');

        // id yang menjadi induk (punya sub-tugas); selebihnya tugas leaf
        $indukIds = $tasks->pluck('parent_id')->filter()->unique();
        $leafIds = $tasks
            ->filter(fn (Task $task) => ! $indukIds->contains($task->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // dependensi dipakai hanya bila kedua ujungnya tugas leaf
        $dependensi = $project->dependencies()->get()
            ->filter(fn (TaskDependency $d) => in_array((int) $d->predecessor_id, $leafIds, true)
                && in_array((int) $d->successor_id, $leafIds, true));

        // FASE 1: topological sort — melempar SchedulingException bila siklus,
        //         sebelum ada satu pun tanggal yang ditulis
        $urutan = $this->topologicalOrder($leafIds, $dependensi);

        // FASE 2: hitung jadwal seluruh leaf sesuai urutan (murni perhitungan)
        $perPenerus = $dependensi->groupBy(fn (TaskDependency $d) => (int) $d->successor_id);
        $jadwal = []; // task_id => ['start' => Carbon, 'end' => Carbon]

        foreach ($urutan as $id) {
            $rencana = $this->computeLeafSchedule(
                $project,
                $petaTugas[$id],
                $perPenerus->get($id, collect()),
                $petaTugas,
                $jadwal
            );

            if ($rencana !== null) {
                $jadwal[$id] = $rencana;
            }
        }

        // FASE 3: tulis jadwal leaf yang berubah (updateQuietly: tanpa event)
        foreach ($jadwal as $id => $rencana) {
            $task = $petaTugas[$id];

            if ($task->start_date && $task->end_date
                && $task->start_date->equalTo($rencana['start'])
                && $task->end_date->equalTo($rencana['end'])) {
                continue; // sudah sesuai, tidak perlu menulis
            }

            $task->updateQuietly([
                'start_date' => $rencana['start'],
                'end_date' => $rencana['end'],
            ]);
        }

        // FASE 4: agregasi tugas induk SETELAH semua leaf selesai
        $this->aggregateParents($tasks, $indukIds);
    }

    /**
     * Tolak penyimpanan dependensi yang membentuk siklus (A → B → A).
     *
     * Dipanggil dari event `saving` TaskDependency SEBELUM baris masuk
     * database, sehingga siklus tidak pernah tersimpan ("perubahan tidak
     * disimpan"). Algoritmanya sama dengan topologicalOrder(); bila baris
     * sedang di-update pasangan bisa muncul dua kali — aman, karena edge
     * ganda tetap menghasilkan indegree yang konsisten.
     */
    public function assertNoCycle(TaskDependency $dependency): void
    {
        $project = $dependency->project;

        if (! $project) {
            return; // proyek belum terisi — validasi penuh menyusul di tahap UI dependensi
        }

        $tasks = $project->tasks()->get();
        $indukIds = $tasks->pluck('parent_id')->filter()->unique();
        $leafIds = $tasks
            ->filter(fn (Task $task) => ! $indukIds->contains($task->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // dependensi tersimpan + calon baris yang sedang disimpan
        $edges = $project->dependencies()->get();
        $edges->push($dependency);

        $this->topologicalOrder($leafIds, $edges);
    }

    /**
     * Urutkan tugas leaf secara topologis (algoritma Kahn):
     * pendahulu selalu muncul sebelum penerusnya.
     *
     * @param  array<int>  $leafIds
     * @param  Collection<int, TaskDependency>  $dependensi
     * @return array<int> id tugas terurut
     *
     * @throws SchedulingException bila ada siklus (mis. A → B → A)
     */
    private function topologicalOrder(array $leafIds, Collection $dependensi): array
    {
        $masuk = array_fill_keys($leafIds, 0); // jumlah pendahulu tiap tugas
        $keluar = []; // adjacency: id pendahulu => [id penerus, ...]

        foreach ($dependensi as $d) {
            $pendahulu = (int) $d->predecessor_id;
            $penerus = (int) $d->successor_id;

            // dependensi yang menyentuh tugas non-leaf diabaikan (lihat catatan kelas)
            if (! isset($masuk[$pendahulu]) || ! isset($masuk[$penerus])) {
                continue;
            }

            $masuk[$penerus]++;
            $keluar[$pendahulu][] = $penerus;
        }

        $antrian = array_keys(array_filter($masuk, fn (int $n) => $n === 0));
        $hasil = [];

        while ($antrian !== []) {
            $id = array_shift($antrian);
            $hasil[] = $id;

            foreach ($keluar[$id] ?? [] as $lanjut) {
                if (--$masuk[$lanjut] === 0) {
                    $antrian[] = $lanjut;
                }
            }
        }

        // sisa yang belum terproses berarti terlibat siklus (termasuk self-loop)
        if (count($hasil) !== count($leafIds)) {
            throw SchedulingException::siklus();
        }

        return $hasil;
    }

    /**
     * Hitung jadwal satu tugas leaf TANPA menulis apa pun.
     *
     * @param  Collection<int, TaskDependency>  $dependensi  dependensi masuk tugas ini
     * @param  Collection<int, Task>  $petaTugas  seluruh tugas proyek, kunci = id
     * @param  array<int, array{start: CarbonInterface, end: CarbonInterface}>  $jadwal  hasil hitungan fase ini (pendahulu lebih dulu)
     * @return array{start: Carbon, end: Carbon}|null null bila tugas belum bisa dijadwalkan
     */
    private function computeLeafSchedule(
        Project $project,
        Task $task,
        Collection $dependensi,
        Collection $petaTugas,
        array $jadwal
    ): ?array {
        // durasi bersumber dari kolom duration; bila kosong pakai rentang tersimpan
        $durasi = $task->duration ?? (($task->start_date && $task->end_date)
            ? $this->kalender->countWorkingDays($project, $task->start_date, $task->end_date)
            : null);

        if ($dependensi->isEmpty()) {
            // ATURAN 6: tanpa predecessor → pakai start_date sendiri.
            // Tugas tanpa jadwal (induk yang belum punya sub-tugas) dibiarkan apa adanya.
            if ($durasi === null || ! $task->start_date) {
                return null;
            }

            $mulai = $task->start_date->copy();
        } else {
            if ($durasi === null) {
                return null;
            }

            $mulai = null;

            foreach ($dependensi as $d) {
                $pendahulu = $petaTugas[(int) $d->predecessor_id] ?? null;

                if (! $pendahulu) {
                    continue;
                }

                // jadwal pendahulu: hasil hitungan fase ini (urutan topologis
                // menjamin pendahulu sudah dihitung lebih dulu); kalau tidak
                // ada, pakai tanggal tersimpan di database
                $pendahuluMulai = $jadwal[(int) $d->predecessor_id]['start'] ?? $pendahulu->start_date;
                $pendahuluAkhir = $jadwal[(int) $d->predecessor_id]['end'] ?? $pendahulu->end_date;

                $kandidat = $this->computeLinkStart(
                    $project,
                    $pendahuluMulai,
                    $pendahuluAkhir,
                    (string) $d->type,
                    (int) $d->lag_days,
                    $durasi
                );

                if ($kandidat === null) {
                    continue; // pendahulu belum berjadwal
                }

                // ATURAN 5: banyak predecessor → ambil jadwal paling lambat
                if ($mulai === null || $kandidat->greaterThan($mulai)) {
                    $mulai = $kandidat;
                }
            }

            if ($mulai === null) {
                return null; // semua pendahulu belum berjadwal
            }
        }

        return [
            'start' => $mulai,
            'end' => $this->kalender->addWorkingDays($project, $mulai, max($durasi - 1, 0)),
        ];
    }

    /**
     * Hitung mulai yang DIWajIBKAN satu tautan dependensi (dalam hari kerja).
     *
     * FS : mulai di hari kerja setelah pendahulu selesai + delay
     * SS : mulai = mulai pendahulu + delay
     * FF : selesai = selesai pendahulu + delay, mulai dihitung mundur (durasi - 1)
     * SF : selesai = mulai pendahulu + delay, mulai dihitung mundur (durasi - 1)
     *
     * @param  CarbonInterface|null  $pendahuluMulai
     * @param  CarbonInterface|null  $pendahuluAkhir
     * @return Carbon|null null bila tanggal acuan pendahulu belum ada
     */
    private function computeLinkStart(
        Project $project,
        CarbonInterface|null $pendahuluMulai,
        CarbonInterface|null $pendahuluAkhir,
        string $type,
        int $delay,
        int $durasi
    ): ?Carbon {
        return match ($type) {
            'FS' => $pendahuluAkhir
                ? $this->kalender->nextWorkingDay($project, $this->kalender->addWorkingDays($project, $pendahuluAkhir, $delay + 1))
                : null,
            'SS' => $pendahuluMulai
                ? $this->kalender->nextWorkingDay($project, $this->kalender->addWorkingDays($project, $pendahuluMulai, $delay))
                : null,
            'FF' => $pendahuluAkhir
                ? $this->startFromFinish(
                    $project,
                    $this->kalender->nextWorkingDay($project, $this->kalender->addWorkingDays($project, $pendahuluAkhir, $delay)),
                    $durasi
                )
                : null,
            'SF' => $pendahuluMulai
                ? $this->startFromFinish(
                    $project,
                    $this->kalender->nextWorkingDay($project, $this->kalender->addWorkingDays($project, $pendahuluMulai, $delay)),
                    $durasi
                )
                : null,
            default => throw SchedulingException::tipeTidakDikenal($type),
        };
    }

    /** Mulai dihitung mundur dari selesai (untuk FF & SF): selesai - (durasi - 1) hari kerja. */
    private function startFromFinish(Project $project, CarbonInterface $selesai, int $durasi): Carbon
    {
        return $this->kalender->addWorkingDays($project, $selesai, -max($durasi - 1, 0));
    }

    /**
     * Agregasi seluruh tugas induk SETELAH leaf selesai dijadwalkan.
     *
     * Diproses dari kedalaman terbesar ke paling atas supaya setiap induk
     * dihitung setelah anaknya final; recomputeSchedule juga meneruskan
     * hitungan ke induk di atasnya (rollup tahap 2 tidak berubah).
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int>  $indukIds
     */
    private function aggregateParents(Collection $tasks, Collection $indukIds): void
    {
        $indukPerId = $tasks->mapWithKeys(fn (Task $task) => [$task->id => $task->parent_id]);

        $tasks
            ->filter(fn (Task $task) => $indukIds->contains($task->id))
            ->sortBy(fn (Task $task) => $this->depth($task, $indukPerId), SORT_NUMERIC, true)
            ->each(fn (Task $task) => $task->recomputeSchedule($this->kalender));
    }

    /** Kedalaman tugas dalam pohon WBS (akar = 0). */
    private function depth(Task $task, Collection $indukPerId): int
    {
        $kedalaman = 0;
        $current = $task->parent_id;

        while ($current !== null && $indukPerId->has($current)) {
            $kedalaman++;
            $current = $indukPerId[$current];

            if ($kedalaman > 50) {
                break; // pengaman bila struktur pohon tidak sehat
            }
        }

        return $kedalaman;
    }
}
