<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;

/**
 * Jalur kritis (Critical Path Method) — tahap 7A, logika murni tanpa UI.
 *
 * Aturan perhitungan:
 * - Hanya tugas TANPA anak (leaf) yang dihitung (cara pengambilan sama
 *   dengan PertController::data).
 * - Durasi memakai kolom `duration` (hari kerja).
 * - SEMUA tipe dependensi (FS/SS/FF/SF) diperlakukan sebagai FS dan
 *   delay (lag_days) DIABAIKAN untuk perhitungan jalur.
 * - Waktu dihitung dalam satuan hari kerja sejak hari ke-0:
 *   ES = max(EF pendahulu), EF = ES + durasi;
 *   backward: LF = min(LS penerus) (sumber akhir = durasi total),
 *   LS = LF - durasi; float = LS - ES; kritis bila float 0.
 */
class CriticalPathService
{
    /**
     * Hitung jalur kritis satu proyek.
     *
     * @return array{
     *     tugas: list<array{id: int, nama: string, durasi: int, es: int, ef: int, ls: int, lf: int, float: int, kritis: bool}>,
     *     jalur: list<array{nama: string, total: int, rute: list<int>}>,
     *     jalur_kritis: list<array{nama: string, total: int, rute: list<int>}>,
     *     tugas_kritis: list<int>,
     *     durasi_total: int
     * }
     */
    public function hitung(Project $project): array
    {
        // --- tugas tanpa anak + dependensi yang kedua ujungnya tugas tanpa anak ---
        $semuaTugas = $project->tasks()->orderBy('id')->get();
        $anakPerInduk = $semuaTugas->groupBy(fn (Task $t) => $t->parent_id);

        $daftarTugas = $semuaTugas
            ->filter(fn (Task $t) => ! $anakPerInduk->has($t->id))
            ->values();
        $idTugas = $daftarTugas->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($idTugas === []) {
            return [
                'tugas' => [],
                'jalur' => [],
                'jalur_kritis' => [],
                'tugas_kritis' => [],
                'durasi_total' => 0,
            ];
        }

        $pendahulu = array_fill_keys($idTugas, []); // id => [id pendahulu, ...]
        $penerus = array_fill_keys($idTugas, []);   // id => [id penerus, ...]

        foreach ($project->dependencies()->orderBy('id')->get() as $d) {
            $p = (int) $d->predecessor_id;
            $s = (int) $d->successor_id;

            // tipe diabaikan (semua diperlakukan FS) dan delay tidak dipakai
            if (isset($pendahulu[$p]) && isset($penerus[$s]) && $p !== $s) {
                $pendahulu[$s][] = $p;
                $penerus[$p][] = $s;
            }
        }

        // --- urutan topologis (Kahn); siklus dicegah model TaskDependency ---
        $masuk = array_map('count', $pendahulu);
        $antrian = [];
        foreach ($masuk as $id => $n) {
            if ($n === 0) {
                $antrian[] = $id;
            }
        }

        $urutan = [];
        while ($antrian !== []) {
            $id = array_shift($antrian);
            $urutan[] = $id;

            foreach ($penerus[$id] as $lanjut) {
                if (--$masuk[$lanjut] === 0) {
                    $antrian[] = $lanjut;
                }
            }
        }

        // --- forward pass: ES / EF ---
        $es = $ef = [];
        foreach ($urutan as $id) {
            $efPendahulu = 0;
            foreach ($pendahulu[$id] as $p) {
                $efPendahulu = max($efPendahulu, $ef[$p]);
            }
            $es[$id] = $efPendahulu;
            $ef[$id] = $efPendahulu + $this->durasi($daftarTugas, $id);
        }

        $durasiTotal = $urutan === [] ? 0 : max($ef);

        // --- backward pass: LF / LS (iterasi urutan topologis terbalik) ---
        $ls = $lf = [];
        foreach (array_reverse($urutan) as $id) {
            $lsPenerus = null;
            foreach ($penerus[$id] as $s) {
                $lsPenerus = $lsPenerus === null ? $ls[$s] : min($lsPenerus, $ls[$s]);
            }
            $lf[$id] = $lsPenerus ?? $durasiTotal;
            $ls[$id] = $lf[$id] - $this->durasi($daftarTugas, $id);
        }

        // --- rincian tiap tugas + daftar kritis (float 0) ---
        $tugas = [];
        $tugasKritis = [];

        foreach ($daftarTugas as $t) {
            $id = (int) $t->id;
            $float = $ls[$id] - $es[$id];

            $tugas[] = [
                'id' => $id,
                'nama' => $t->name,
                'durasi' => $this->durasi($daftarTugas, $id),
                'es' => $es[$id],
                'ef' => $ef[$id],
                'ls' => $ls[$id],
                'lf' => $lf[$id],
                'float' => $float,
                'kritis' => $float === 0,
            ];

            if ($float === 0) {
                $tugasKritis[] = $id;
            }
        }

        // --- SEMUA jalur dari tugas awal (tanpa pendahulu) ke tugas akhir ---
        $mulai = array_keys(array_filter($pendahulu, fn (array $p) => $p === []));

        $jalur = [];
        $kunjung = function (int $id, array $jalan, int $total) use (&$kunjung, &$jalur, $penerus, $daftarTugas): void {
            $jalan[] = $id;
            $total += $this->durasi($daftarTugas, $id);

            if ($penerus[$id] === []) {
                $jalur[] = [
                    'nama' => implode(' -> ', array_map(
                        fn (int $i) => $daftarTugas->firstWhere('id', $i)->name,
                        $jalan
                    )),
                    'total' => $total,
                    'rute' => $jalan,
                ];

                return;
            }

            foreach ($penerus[$id] as $s) {
                $kunjung($s, $jalan, $total);
            }
        };

        foreach ($mulai as $id) {
            $kunjung($id, [], 0);
        }

        // --- jalur kritis = semua jalur terpanjang; durasi total proyek ---
        $jalurKritis = [];
        $terpanjang = 0;

        foreach ($jalur as $j) {
            if ($j['total'] > $terpanjang) {
                $terpanjang = $j['total'];
                $jalurKritis = [$j];
            } elseif ($j['total'] === $terpanjang) {
                $jalurKritis[] = $j;
            }
        }

        return [
            'tugas' => $tugas,
            'jalur' => $jalur,
            'jalur_kritis' => $jalurKritis,
            'tugas_kritis' => $tugasKritis,
            'durasi_total' => $durasiTotal,
        ];
    }

    /** Durasi hari kerja satu tugas (0 bila kolom kosong). */
    private function durasi($daftarTugas, int $id): int
    {
        return (int) ($daftarTugas->firstWhere('id', $id)->duration ?? 0);
    }
}
