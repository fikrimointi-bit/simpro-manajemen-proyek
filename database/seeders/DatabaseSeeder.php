<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\SchedulerService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder contoh SIMPRO (tahap 7B-2b) — aman dijalankan ulang:
 * setiap bagian dilewati bila datanya sudah ada (tanpa duplikat).
 *
 * Pola penjadwalan menirui alur form (TaskController::store): tanggal awal
 * tugas tanpa predecessor dihitung via CalendarService, dependensi dibuat
 * apa adanya, lalu SchedulerService::scheduleProject() menghitung jadwal
 * akhir seluruh proyek.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Seed the application's database. */
    public function run(): void
    {
        $this->seederAkun();
        $this->seederProyekSdlc();
        $this->seederProyekJalurKritis();
    }

    /** Akun demo — dilewati bila sudah ada. */
    private function seederAkun(): void
    {
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }

    /**
     * Proyek 1 "Contoh SDLC": mulai Selasa 30 Juni 2015, weekend Sabtu-Minggu
     * libur, libur sekali 17 Juli 2015; 6 tugas berantai + 7 anggota tim.
     */
    private function seederProyekSdlc(): void
    {
        $proyek = Project::firstOrCreate(
            ['name' => 'Contoh SDLC'],
            [
                'start_date' => '2015-06-30', // Selasa
                'weekend_days' => [6, 0],
                'weekend_mode' => 'no_work',
            ],
        );

    if (! $proyek->holidays()->whereDate('date', '2015-07-17')->exists()) {
        $proyek->holidays()->create([
            'date'         => '2015-07-17',
            'description'  => 'Libur Tim',
            'is_recurring' => false,
        ]);
    }
        if ($proyek->tasks()->exists()) {
            return; // sudah ter-seed — jalankan ulang tanpa duplikat
        }

        $kalender = app(CalendarService::class);
        $tugas = [];

        // tugas pertama berjadwal mandiri (pola form: isi tanggal + durasi)
        $tugas['Analisis Kebutuhan'] = Task::create([
            'project_id' => $proyek->id,
            'name' => 'Analisis Kebutuhan',
            'start_date' => '2015-06-30',
            'end_date' => $kalender->addWorkingDays($proyek, '2015-06-30', 4)->toDateString(),
            'duration' => 5,
            'priority' => 'normal',
            'position' => 0,
        ]);

        // sisanya tanpa tanggal mulai — jadwal menyusul dari predecessor
        // (pola form: saat ada predecessor hanya durasi yang diisi)
        $berikut = [
            'Analisis Sistem' => 8,
            'Perancangan' => 7,
            'Implementasi' => 5,
            'Pengujian' => 2,
            'Deployment' => 2,
        ];

        $posisi = 1;
        foreach ($berikut as $nama => $durasi) {
            $tugas[$nama] = Task::create([
                'project_id' => $proyek->id,
                'name' => $nama,
                'duration' => $durasi,
                'priority' => 'normal',
                'position' => $posisi++,
            ]);
        }

        // dependensi berantai: SS+3, SS+5, lalu FS+0 sampai Deployment
        $dependensi = [
            ['Analisis Kebutuhan', 'Analisis Sistem', 'SS', 3],
            ['Analisis Sistem', 'Perancangan', 'SS', 5],
            ['Perancangan', 'Implementasi', 'FS', 0],
            ['Implementasi', 'Pengujian', 'FS', 0],
            ['Pengujian', 'Deployment', 'FS', 0],
        ];

        foreach ($dependensi as [$dari, $ke, $tipe, $jeda]) {
            TaskDependency::create([
                'project_id' => $proyek->id,
                'predecessor_id' => $tugas[$dari]->id,
                'successor_id' => $tugas[$ke]->id,
                'type' => $tipe,
                'lag_days' => $jeda,
            ]);
        }

        // hitung jadwal akhir seluruh proyek (sama seperti akhir alur form)
        app(SchedulerService::class)->scheduleProject($proyek);

        // --- 7 anggota tim: 1 PM, 2 Analis, 2 Programmer, 2 Tester ---
        $daftar = [
            ['Budi Santoso', 'Project Manager', 15_000_000, '#4f46e5'],
            ['Sari Anindita', 'Analis', 10_000_000, '#16a34a'],
            ['Rudi Hartono', 'Analis', 10_000_000, '#d97706'],
            ['Dewi Lestari', 'Programmer', 12_000_000, '#dc2726'],
            ['Eko Prasetyo', 'Programmer', 12_000_000, '#0891b2'],
            ['Fitri Handayani', 'Tester', 9_000_000, '#7c3aed'],
            ['Gilang Ramadhan', 'Tester', 9_000_000, '#db2777'],
        ];

        $idPerPeran = [];
        foreach ($daftar as $i => [$nama, $peran, $gaji, $warna]) {
            $anggota = Resource::create([
                'project_id' => $proyek->id,
                'name' => $nama,
                'phone' => '0812-3456-78'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $nama)).'@sdlc.test',
                'role' => $peran,
                'salary' => $gaji,
                'color' => $warna,
            ]);
            $idPerPeran[$peran][] = $anggota->id;
        }

        // penugasan tugas sesuai peran anggota
        $penugasan = [
            'Analisis Kebutuhan' => ['Project Manager'],
            'Analisis Sistem' => ['Analis'],
            'Perancangan' => ['Analis'],
            'Implementasi' => ['Programmer'],
            'Pengujian' => ['Tester'],
            'Deployment' => ['Programmer'],
        ];

        foreach ($penugasan as $namaTugas => $peran) {
            $ids = [];
            foreach ($peran as $p) {
                $ids = array_merge($ids, $idPerPeran[$p]);
            }
            $tugas[$namaTugas]->resources()->sync($ids);
        }
    }

    /**
     * Proyek 2 "Contoh Jalur Kritis": graf A–J (semua FS delay 0),
     * mulai Senin 5 Oktober 2026 — total 33 hari kerja, jalur B-E-H-J.
     */
    private function seederProyekJalurKritis(): void
    {
        $proyek = Project::firstOrCreate(
            ['name' => 'Contoh Jalur Kritis'],
            [
                'start_date' => '2026-10-05', // Senin
                'weekend_days' => [6, 0],
                'weekend_mode' => 'no_work',
            ],
        );

        if ($proyek->tasks()->exists()) {
            return; // sudah ter-seed — jalankan ulang tanpa duplikat
        }

        $kalender = app(CalendarService::class);
        $tugas = [];
        $posisi = 0;

        // A, B, C tanpa predecessor: jadwal mandiri sejak awal proyek
        foreach (['A' => 1, 'B' => 2, 'C' => 3] as $huruf => $durasi) {
            $tugas[$huruf] = Task::create([
                'project_id' => $proyek->id,
                'name' => $huruf,
                'start_date' => '2026-10-05',
                'end_date' => $kalender->addWorkingDays($proyek, '2026-10-05', $durasi - 1)->toDateString(),
                'duration' => $durasi,
                'priority' => 'normal',
                'position' => $posisi++,
            ]);
        }

        // sisanya hanya berdurasi — jadwal dihitung SchedulerService
        foreach (['D' => 5, 'E' => 7, 'F' => 4, 'G' => 12, 'H' => 21, 'I' => 2, 'J' => 3] as $huruf => $durasi) {
            $tugas[$huruf] = Task::create([
                'project_id' => $proyek->id,
                'name' => $huruf,
                'duration' => $durasi,
                'priority' => 'normal',
                'position' => $posisi++,
            ]);
        }

        // semua dependensi FS delay 0
        $dependensi = [
            ['A', 'D'], ['B', 'E'], ['B', 'F'], ['C', 'G'], ['D', 'H'],
            ['E', 'H'], ['G', 'I'], ['F', 'J'], ['H', 'J'], ['I', 'J'],
        ];

        foreach ($dependensi as [$dari, $ke]) {
            TaskDependency::create([
                'project_id' => $proyek->id,
                'predecessor_id' => $tugas[$dari]->id,
                'successor_id' => $tugas[$ke]->id,
                'type' => 'FS',
                'lag_days' => 0,
            ]);
        }

        // hitung jadwal akhir seluruh proyek (sama seperti akhir alur form)
        app(SchedulerService::class)->scheduleProject($proyek);
    }
}
