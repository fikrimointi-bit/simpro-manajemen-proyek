<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Services\CriticalPathService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji DatabaseSeeder (tahap 7B-2b): dua proyek contoh tersedia dengan
 * jumlah tugas & anggota benar, jalur kritis proyek kedua = 33 hari
 * (B-E-H-J), seeder aman dijalankan ulang tanpa duplikat.
 *
 * Jalankan: php artisan test --filter=SeederTest
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dua_proyek_contoh_terseeding_dengan_jumlah_benar(): void
    {
        $this->seed(DatabaseSeeder::class);

        // --- kedua proyek ada ---
        $sdlc = Project::where('name', 'Contoh SDLC')->first();
        $kritis = Project::where('name', 'Contoh Jalur Kritis')->first();
        $this->assertNotNull($sdlc, 'proyek Contoh SDLC harus ada');
        $this->assertNotNull($kritis, 'proyek Contoh Jalur Kritis harus ada');

        // --- pengaturan & hari libur proyek pertama ---
        $this->assertSame('2015-06-30', $sdlc->start_date->toDateString());
        $this->assertSame([6, 0], $sdlc->weekend_days);
        $this->assertSame('no_work', $sdlc->weekend_mode);

        $libur = $sdlc->holidays()->get();
        $this->assertCount(1, $libur, 'hanya libur sekali 17 Juli 2015');
        $this->assertSame('2015-07-17', $libur->first()->date->toDateString());
        $this->assertFalse($libur->first()->is_recurring);

        // --- proyek 1: 6 tugas + 7 anggota (1 PM, 2 analis, 2 programmer, 2 tester) ---
        $this->assertSame(6, $sdlc->tasks()->count());
        $this->assertSame(
            ['Analisis Kebutuhan', 'Analisis Sistem', 'Perancangan', 'Implementasi', 'Pengujian', 'Deployment'],
            $sdlc->tasks()->orderBy('position')->pluck('name')->all()
        );
        $this->assertSame([5, 8, 7, 5, 2, 2], $sdlc->tasks()->orderBy('position')->pluck('duration')->all());

        $anggota = $sdlc->resources()->orderBy('id')->get();
        $this->assertCount(7, $anggota);
        $this->assertSame(
            ['Project Manager', 'Analis', 'Analis', 'Programmer', 'Programmer', 'Tester', 'Tester'],
            $anggota->pluck('role')->all()
        );
        // tiap anggota lengkap: telepon, email, gaji
        foreach ($anggota as $sumber) {
            $this->assertNotEmpty($sumber->phone, 'telepon terisi');
            $this->assertNotEmpty($sumber->email, 'email terisi');
            $this->assertIsInt($sumber->salary);
            $this->assertGreaterThan(0, $sumber->salary);
        }
        // seluruh anggota bertugas (bukan dibiarkan tanpa penugasan)
        $terpakai = Task::whereIn('id', $sdlc->tasks()->pluck('id'))
            ->with('resources')
            ->get()
            ->flatMap(fn (Task $t) => $t->resources)
            ->unique('id')
            ->count();
        $this->assertSame(7, $terpakai, 'ketujuh anggota ditugaskan sesuai peran');

        // dependensi SS+3, SS+5, lalu FS berantai (5 relasi)
        $this->assertSame(5, $sdlc->dependencies()->count());
        $this->assertSame(
            [['SS', 3], ['SS', 5], ['FS', 0], ['FS', 0], ['FS', 0]],
            $sdlc->dependencies()->orderBy('id')->get()
                ->map(fn ($d) => [$d->type, $d->lag_days])->all()
        );

        // jadwal proyek 1 dihitung SchedulerService: semua tugas berjangka
        $tanpaJadwal = $sdlc->tasks()->whereNull('start_date')->count();
        $this->assertSame(0, $tanpaJadwal, 'seluruh tugas SDLC berjadwal');

        // --- proyek 2: 10 tugas, tanpa anggota, jadwal & dependensi benar ---
        $this->assertSame(10, $kritis->tasks()->count());
        $this->assertSame(0, $kritis->resources()->count());
        $this->assertSame(10, $kritis->dependencies()->count());
        $this->assertSame(
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
            $kritis->tasks()->orderBy('position')->pluck('name')->all()
        );
        $this->assertSame(
            [1, 2, 3, 5, 7, 4, 12, 21, 2, 3],
            $kritis->tasks()->orderBy('position')->pluck('duration')->all()
        );
        $this->assertSame('2026-10-05', $kritis->start_date->toDateString());
        $this->assertSame(0, $kritis->tasks()->whereNull('start_date')->count());

        // --- jalur kritis proyek kedua: total 33, jalur B-E-H-J ---
        $hasil = app(CriticalPathService::class)->hitung($kritis);
        $this->assertSame(33, $hasil['durasi_total']);
        $this->assertCount(1, $hasil['jalur_kritis']);
        $this->assertSame('B -> E -> H -> J', $hasil['jalur_kritis'][0]['nama']);
        $this->assertSame(33, $hasil['jalur_kritis'][0]['total']);
        $this->assertCount(4, $hasil['jalur'], 'empat jalur awal-akhir');
    }

    public function test_seeder_aman_dijalankan_ulang(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // kedua kali: tidak boleh menduplikat apa pun

        $this->assertSame(2, Project::count(), 'persis dua proyek');
        $this->assertSame(16, Task::count(), '6 + 10 tugas, tanpa duplikat');
        $this->assertSame(7, Project::where('name', 'Contoh SDLC')->firstOrFail()
            ->resources()->count());

        // jalur kritis tetap sama setelah dijalankan ulang
        $kritis = Project::where('name', 'Contoh Jalur Kritis')->firstOrFail();
        $hasil = app(CriticalPathService::class)->hitung($kritis);
        $this->assertSame(33, $hasil['durasi_total']);
        $this->assertSame('B -> E -> H -> J', $hasil['jalur_kritis'][0]['nama']);
    }
}
