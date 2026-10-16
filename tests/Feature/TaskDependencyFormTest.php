<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Support\Tanggal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji antarmuka predecessor (tahap 3B): simpan lewat form POST, penolakan
 * siklus sebagai error form (bukan halaman error), dan jadwal ulang yang
 * tampil di tabel tugas.
 *
 * Jalankan: php artisan test --filter=TaskDependencyFormTest
 */
class TaskDependencyFormTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(): Project
    {
        return Project::create([
            'name' => 'Proyek Uji Predecessor',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    /** Data tugas valid (durasi dalam hari kerja). */
    private function dataTugas(array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Tugas',
            'priority' => 'normal',
            'start_date' => '2026-10-12', // Senin
            'duration' => 2,
        ], $tambahan);
    }

    /** Simpan tugas tanpa predecessor lewat POST, kembalikan modelnya. */
    private function buatTugas(Project $proyek, string $nama): Task
    {
        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas(['name' => $nama]))
            ->assertSessionHasNoErrors();

        return $proyek->tasks()->where('name', $nama)->firstOrFail();
    }

    public function test_simpan_tugas_dengan_predecessor_lewat_post(): void
    {
        $proyek = $this->proyek();
        $a = $this->buatTugas($proyek, 'Analisis Kebutuhan'); // 12-13 Okt

        // B TANPA tanggal mulai — input dinonaktifkan saat ada predecessor
        $this->post(route('projects.tasks.store', $proyek), [
            'name' => 'Pengembangan',
            'priority' => 'normal',
            'duration' => 3,
            'predecessors' => [
                ['predecessor_id' => $a->id, 'type' => 'SS', 'lag_days' => 3],
            ],
        ])
            ->assertRedirect(route('projects.tasks.index', $proyek))
            ->assertSessionHasNoErrors();

        $b = $proyek->tasks()->where('name', 'Pengembangan')->firstOrFail();

        // dependensi tersimpan sesuai kolom: successor = tugas yang menunggu
        $dependensi = TaskDependency::where('successor_id', $b->id)->firstOrFail();
        $this->assertSame($a->id, $dependensi->predecessor_id);
        $this->assertSame($proyek->id, $dependensi->project_id);
        $this->assertSame('SS', $dependensi->type);
        $this->assertSame(3, $dependensi->lag_days);

        // jadwal dihitung ulang dari predecessor:
        // SS+3 → mulai = 12 Okt + 3 hari kerja = 15 Okt (Kamis),
        // durasi 3 → selesai = hari kerja ke-2 setelahnya = 19 Okt (Senin)
        $this->assertSame('2026-10-15', $b->start_date->toDateString());
        $this->assertSame('2026-10-19', $b->end_date->toDateString());

        // tabel menampilkan tanggal terbaru + format kolom Predecessor
        $this->get(route('projects.tasks.index', $proyek))
            ->assertStatus(200)
            ->assertSee(Tanggal::pendek($b->start_date))
            ->assertSee('Analisis Kebutuhan (SS+3)');

        // halaman edit tampil (baris predecessor ter-populasi dari data tersimpan)
        $this->get(route('projects.tasks.edit', [$proyek, $b]))->assertStatus(200);
    }

    public function test_siklus_dependensi_ditolak_lewat_form(): void
    {
        $proyek = $this->proyek();
        $a = $this->buatTugas($proyek, 'Analisis Kebutuhan');
        $b = $this->buatTugas($proyek, 'Pengembangan');

        // ubah B: pendahulu A → valid dan tersimpan (tanpa start_date, meniru
        // input yang dinonaktifkan saat ada predecessor)
        $this->put(route('projects.tasks.update', [$proyek, $b]), [
            'name' => 'Pengembangan',
            'priority' => 'normal',
            'duration' => 4,
            'predecessors' => [
                ['predecessor_id' => $a->id, 'type' => 'FS', 'lag_days' => 0],
            ],
        ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TaskDependency::count());

        // ubah A: pendahulu B → siklus A → B → A;
        // ditolak sebagai error form, bukan halaman error
        $this->put(route('projects.tasks.update', [$proyek, $a]), [
            'name' => 'Analisis Kebutuhan',
            'priority' => 'normal',
            'duration' => 2,
            'predecessors' => [
                ['predecessor_id' => $b->id, 'type' => 'FS', 'lag_days' => 0],
            ],
        ])
            ->assertStatus(302) // kembali ke form dengan error, bukan 500
            ->assertSessionHasErrors('predecessors.0.predecessor_id');

        // hanya error siklus itu sendiri (tunggal, di field yang tepat)
        $this->assertCount(1, session('errors')->all());

        // perubahan tidak disimpan: milik B tetap satu-satunya dependensi
        $this->assertSame(1, TaskDependency::count());
        $this->assertSame($a->id, TaskDependency::firstOrFail()->predecessor_id);
        $this->assertSame($b->id, TaskDependency::firstOrFail()->successor_id);

        // jadwal A tidak ikut berubah karena validasi gagal sebelum controller
        $a->refresh();
        $this->assertSame('2026-10-12', $a->start_date->toDateString());
        $this->assertSame('2026-10-13', $a->end_date->toDateString());
    }
}
