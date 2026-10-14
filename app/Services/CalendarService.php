<?php

namespace App\Services;

use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Logika kalender kerja sebuah proyek.
 *
 * Layanan ini murni logika bisnis: tidak menyentuh request/session/view,
 * hanya membaca setting proyek (weekend_days, weekend_mode) dan tabel holidays.
 * Input boleh berupa string tanggal maupun Carbon, selalu dinormalkan ke tengah malam.
 */
class CalendarService
{
    /** Cache libur per proyek supaya loop hitung hari tidak query berulang. */
    private array $cacheLibur = [];

    /**
     * Apakah tanggal ini hari kerja?
     *
     * Urutan pengecekan:
     * 1. Tanggal masuk daftar hari libur proyek (sekali atau berulang) -> libur.
     * 2. Tanggal jatuh di hari weekend:
     *    - weekend_mode 'no_work' -> libur,
     *    - weekend_mode 'work'    -> tetap hari kerja.
     * 3. Selain itu -> hari kerja.
     */
    public function isWorkingDay(Project $project, CarbonInterface|string $date): bool
    {
        $tanggal = $this->normalisasi($date);

        if ($this->isHoliday($project, $tanggal)) {
            return false;
        }

        if ($this->isWeekend($project, $tanggal) && $project->weekend_mode === 'no_work') {
            return false;
        }

        return true;
    }

    /**
     * Apakah tanggal ini termasuk hari libur proyek?
     *
     * Libur 'sekali' (is_recurring = false) hanya cocok pada tanggal yang persis sama.
     * Libur 'berulang' (is_recurring = true) cocok setiap tahun pada bulan + tanggal yang sama.
     */
    public function isHoliday(Project $project, CarbonInterface|string $date): bool
    {
        $tanggal = $this->normalisasi($date);
        $libur = $this->liburProyek($project);

        // Libur satu kali dipetakan kunci tanggal penuh (Y-m-d)
        if (isset($libur['sekali'][$tanggal->toDateString()])) {
            return true;
        }

        // Libur berulang dipetakan kunci bulan-hari (m-d)
        return isset($libur['berulang'][$tanggal->format('m-d')]);
    }

    /**
     * Geser tanggal sejumlah N hari kerja dari tanggal awal.
     *
     * Tanggal awal TIDAK dihitung sebagai hari kerja pertama; hasilnya adalah
     * hari kerja ke-N setelah tanggal awal. Contoh: Jumat + 1 hari kerja = Senin
     * (dengan weekend Sabtu-Minggu libur). N boleh 0 (tanggal itu sendiri,
     * walau libur) dan boleh negatif untuk menggeser ke belakang.
     */
    public function addWorkingDays(Project $project, CarbonInterface|string $date, int $n): Carbon
    {
        $tanggal = $this->normalisasi($date);
        $sisa = abs($n);
        $arah = $n < 0 ? -1 : 1;

        while ($sisa > 0) {
            $tanggal->addDays($arah);

            if ($this->isWorkingDay($project, $tanggal)) {
                $sisa--;
            }
        }

        return $tanggal;
    }

    /**
     * Hari kerja terdekat pada atau setelah tanggal tersebut.
     *
     * Jika tanggal sudah hari kerja, tanggal itu sendiri yang dikembalikan;
     * jika libur (weekend maupun hari libur proyek), digeser maju satu hari
     * berulang sampai menemukan hari kerja.
     */
    public function nextWorkingDay(Project $project, CarbonInterface|string $date): Carbon
    {
        $tanggal = $this->normalisasi($date);

        while (! $this->isWorkingDay($project, $tanggal)) {
            $tanggal->addDay();
        }

        return $tanggal;
    }

    /**
     * Hitung jumlah hari kerja dalam rentang tanggal, inklusif kedua ujungnya.
     *
     * Contoh: Senin..Jumat (tanpa libur lain) = 5 hari kerja.
     * Jika rentang terbalik (akhir sebelum awal), hasilnya 0.
     */
    public function countWorkingDays(Project $project, CarbonInterface|string $start, CarbonInterface|string $end): int
    {
        $mulai = $this->normalisasi($start);
        $akhir = $this->normalisasi($end);

        if ($akhir->lt($mulai)) {
            return 0;
        }

        $jumlah = 0;

        // iterasi per hari, aman meski rentangnya bertahun-tahun
        while ($mulai->lte($akhir)) {
            if ($this->isWorkingDay($project, $mulai)) {
                $jumlah++;
            }

            $mulai->addDay();
        }

        return $jumlah;
    }

    /** Apakah tanggal tersebut jatuh di hari weekend sesuai setting proyek? */
    private function isWeekend(Project $project, CarbonInterface $tanggal): bool
    {
        $weekendDays = $project->weekend_days ?? [];

        return in_array((int) $tanggal->dayOfWeek, array_map('intval', $weekendDays), true);
    }

    /** Normalisasi input jadi Carbon tengah malam (time zone aplikasi). */
    private function normalisasi(CarbonInterface|string $date): Carbon
    {
        return $date instanceof CarbonInterface
            ? Carbon::instance($date)->startOfDay()
            : Carbon::parse($date)->startOfDay();
    }

    /**
     * Ambil daftar hari libur proyek dalam bentuk lookup, di-cache per proyek.
     * Return: ['sekali' => ['Y-m-d' => true], 'berulang' => ['m-d' => true]]
     */
    private function liburProyek(Project $project): array
    {
        if (! isset($this->cacheLibur[$project->id])) {
            $sekali = [];
            $berulang = [];

            foreach ($project->holidays()->get() as $holiday) {
                if ($holiday->is_recurring) {
                    // berulang: kunci hanya bulan + tanggal
                    $berulang[$holiday->date->format('m-d')] = true;
                } else {
                    $sekali[$holiday->date->toDateString()] = true;
                }
            }

            $this->cacheLibur[$project->id] = compact('sekali', 'berulang');
        }

        return $this->cacheLibur[$project->id];
    }
}
