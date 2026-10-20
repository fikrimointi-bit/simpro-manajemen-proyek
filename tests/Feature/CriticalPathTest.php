<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\CriticalPathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji jalur kritis (tahap 7A): forward/backward pass, float, SEMUA jalur
 * dari awal ke akhir, jalur terpanjang = jalur kritis, durasi total proyek,
 * dan ketidakpekaan durasi total terhadap perpanjangan jalur non-kritis.
 *
 * Jalankan: php artisan test --filter=CriticalPathTest
 */
class CriticalPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proyek contoh (semua dependensi FS, delay 0):
     *   A=1, B=2, C=3 (tanpa predecessor)
     *   D=5 setelah A; E=7 & F=4 setelah B; G=12 setelah C
     *   H=21 setelah D dan E; I=2 setelah G; J=3 setelah F, H, dan I
     *
     * @return array{0: Project, 1: array<string, Task>} huruf => model tugas
     */
    private function proyekContoh(): array
    {
        $proyek = Project::create([
            'name' => 'Proyek Uji Jalur Kritis',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);

        $tugas = [];
        foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 5, 'E' => 7, 'F' => 4, 'G' => 12, 'H' => 21, 'I' => 2, 'J' => 3] as $huruf => $durasi) {
            $tugas[$huruf] = Task::create([
                'project_id' => $proyek->id,
                'name' => $huruf,
                'duration' => $durasi,
                'priority' => 'normal',
            ]);
        }

        $hubung = fn (string $pred, string $succ) => TaskDependency::create([
            'project_id' => $proyek->id,
            'predecessor_id' => $tugas[$pred]->id,
            'successor_id' => $tugas[$succ]->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);

        $hubung('A', 'D'); // D setelah A
        $hubung('B', 'E'); // E setelah B
        $hubung('B', 'F'); // F setelah B
        $hubung('C', 'G'); // G setelah C
        $hubung('D', 'H'); // H setelah D dan E
        $hubung('E', 'H');
        $hubung('G', 'I'); // I setelah G
        $hubung('F', 'J'); // J setelah F, H, dan I
        $hubung('H', 'J');
        $hubung('I', 'J');

        return [$proyek, $tugas];
    }

    public function test_semua_jalur_dan_angka_target(): void
    {
        [$proyek] = $this->proyekContoh();
        $hasil = app(CriticalPathService::class)->hitung($proyek);

        // --- SEMUA jalur dari tugas awal ke tugas akhir + total durasinya ---
        $this->assertCount(4, $hasil['jalur'], 'harus ada persis 4 jalur');

        $totalPerJalur = [];
        foreach ($hasil['jalur'] as $jalur) {
            $this->assertArrayHasKey('nama', $jalur);
            $this->assertArrayHasKey('total', $jalur);
            $totalPerJalur[$jalur['nama']] = $jalur['total'];
        }

        $this->assertSame(30, $totalPerJalur['A -> D -> H -> J']);
        $this->assertSame(33, $totalPerJalur['B -> E -> H -> J']);
        $this->assertSame(9, $totalPerJalur['B -> F -> J']);
        $this->assertSame(20, $totalPerJalur['C -> G -> I -> J']);

        // --- jalur kritis = jalur terpanjang ---
        $this->assertCount(1, $hasil['jalur_kritis'], 'hanya B-E-H-J yang terpanjang');
        $this->assertSame('B -> E -> H -> J', $hasil['jalur_kritis'][0]['nama']);
        $this->assertSame(33, $hasil['jalur_kritis'][0]['total']);

        // --- durasi total proyek = panjang jalur kritis ---
        $this->assertSame(33, $hasil['durasi_total']);
    }

    public function test_forward_backward_pass_dan_float(): void
    {
        [$proyek] = $this->proyekContoh();
        $hasil = app(CriticalPathService::class)->hitung($proyek);

        $t = collect($hasil['tugas'])->keyBy('nama');
        $this->assertCount(10, $t, 'hanya tugas tanpa anak yang dihitung');

        // --- forward pass: ES/EF tiap tugas ---
        $harap = [
            // nama  => [es, ef, ls, lf, float]
            'A' => [0, 1, 3, 4, 3],
            'B' => [0, 2, 0, 2, 0],
            'C' => [0, 3, 13, 16, 13],
            'D' => [1, 6, 4, 9, 3],
            'E' => [2, 9, 2, 9, 0],
            'F' => [2, 6, 26, 30, 24],
            'G' => [3, 15, 16, 28, 13],
            'H' => [9, 30, 9, 30, 0],
            'I' => [15, 17, 28, 30, 13],
            'J' => [30, 33, 30, 33, 0],
        ];

        foreach ($harap as $nama => [$es, $ef, $ls, $lf, $float]) {
            $this->assertSame($es, $t[$nama]['es'], "ES tugas $nama");
            $this->assertSame($ef, $t[$nama]['ef'], "EF tugas $nama");
            $this->assertSame($ls, $t[$nama]['ls'], "LS tugas $nama");
            $this->assertSame($lf, $t[$nama]['lf'], "LF tugas $nama");
            $this->assertSame($float, $t[$nama]['float'], "float tugas $nama");
            $this->assertSame($float === 0, $t[$nama]['kritis'], "status kritis tugas $nama");
        }

        // --- tugas kritis = float 0 (id, bukan nama) ---
        $idHuruf = [];
        foreach ($hasil['tugas'] as $rincian) {
            $idHuruf[$rincian['nama']] = $rincian['id'];
        }
        $this->assertSame(
            [$idHuruf['B'], $idHuruf['E'], $idHuruf['H'], $idHuruf['J']],
            $hasil['tugas_kritis'],
            'tugas kritis = B, E, H, J (float 0)'
        );
    }

    public function test_mengubah_durasi_f_tidak_mengubah_durasi_total(): void
    {
        [$proyek, $tugas] = $this->proyekContoh();
        $layanan = app(CriticalPathService::class);

        // perpanjang jalur NON-kritis B-F-J dari 9 menjadi 12 hari
        $tugas['F']->updateQuietly(['duration' => 7]);

        $hasil = $layanan->hitung($proyek);

        // jalur B-F-J ikut bertambah ...
        $totalPerJalur = [];
        foreach ($hasil['jalur'] as $jalur) {
            $totalPerJalur[$jalur['nama']] = $jalur['total'];
        }
        $this->assertSame(12, $totalPerJalur['B -> F -> J']);

        // ... tapi durasi total proyek TETAP 33 dan jalur kritis tidak berubah
        $this->assertSame(33, $hasil['durasi_total']);
        $this->assertCount(1, $hasil['jalur_kritis']);
        $this->assertSame('B -> E -> H -> J', $hasil['jalur_kritis'][0]['nama']);
        $this->assertSame(33, $hasil['jalur_kritis'][0]['total']);
    }
}
