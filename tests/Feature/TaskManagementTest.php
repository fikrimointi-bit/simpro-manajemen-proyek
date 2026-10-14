<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji CRUD tugas bersarang: hitungan tanggal selesai otomatis,
 * agregat tugas induk, cegah loop induk, dan hapus beserta sub-tugas.
 * Jalankan: php artisan test --filter=TaskManagementTest
 */
class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(): Project
    {
        return Project::create([
            'name' => 'Proyek Uji Tugas',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    /** Data tugas anak yang valid (durasi dalam hari kerja). */
    private function dataAnak(array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Tugas Anak',
            'priority' => 'normal',
            'color' => '#22c55e',
            'start_date' => '2026-10-12', // Senin
            'duration' => 5,
        ], $tambahan);
    }

    public function test_tugas_anak_menghitung_end_date_dari_hari_kerja(): void
    {
        $proyek = $this->proyek();

        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak())
            ->assertRedirect(route('projects.tasks.index', $proyek))
            ->assertSessionHasNoErrors();

        $task = $proyek->tasks()->first();

        // Senin + 4 hari kerja lagi = Jumat pekan yang sama
        $this->assertSame('2026-10-12', $task->start_date->toDateString());
        $this->assertSame('2026-10-16', $task->end_date->toDateString());
        $this->assertSame(5, $task->duration);
    }

    public function test_durasi_menembus_weekend_dan_hari_libur(): void
    {
        $proyek = $this->proyek();
        // libur sekali pada Rabu
        $proyek->holidays()->create(['date' => '2026-10-14', 'is_recurring' => false]);

        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak(['duration' => 3]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Senin + 2 hari kerja berikutnya (Selasa, lalu Kamis karena Rabu libur)
        $this->assertSame('2026-10-15', $proyek->tasks()->first()->end_date->toDateString());
    }

    public function test_tugas_induk_mengagregasi_anaknya(): void
    {
        $proyek = $this->proyek();

        // induk tanpa jadwal
        $this->post(route('projects.tasks.store', $proyek), ['name' => 'Induk', 'priority' => 'normal'])
            ->assertSessionHasNoErrors();
        $induk = $proyek->tasks()->first();
        $this->assertNull($induk->start_date);
        $this->assertNull($induk->end_date);

        // dua anak dengan rentang berbeda
        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak([
            'name' => 'Anak A', 'parent_id' => $induk->id, 'start_date' => '2026-10-12', 'duration' => 5,
        ]))->assertSessionHasNoErrors();
        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak([
            'name' => 'Anak B', 'parent_id' => $induk->id, 'start_date' => '2026-10-19', 'duration' => 2,
        ]))->assertSessionHasNoErrors();

        $induk->refresh();

        // mulai = anak paling awal, selesai = anak paling akhir,
        // durasi = hari kerja inklusif (5 + 2 = 7)
        $this->assertSame('2026-10-12', $induk->start_date->toDateString());
        $this->assertSame('2026-10-20', $induk->end_date->toDateString());
        $this->assertSame(7, $induk->duration);
    }

    public function test_cegah_loop_induk(): void
    {
        $proyek = $this->proyek();
        $this->post(route('projects.tasks.store', $proyek), ['name' => 'A', 'priority' => 'normal'])
            ->assertSessionHasNoErrors();
        $a = $proyek->tasks()->first();
        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak(['name' => 'B', 'parent_id' => $a->id]))
            ->assertSessionHasNoErrors();
        $b = $proyek->tasks()->where('name', 'B')->first();

        // A tidak boleh menjadi anak B (turunannya sendiri);
        // jadwal dikirim lengkap agar error yang muncul murni dari cegah loop
        $this->put(route('projects.tasks.update', [$proyek, $a]), [
            'name' => 'A', 'priority' => 'normal', 'parent_id' => $b->id,
            'start_date' => '2026-10-05', 'duration' => 1,
        ])->assertSessionHasErrors('parent_id');

        // tugas tidak boleh menjadi induk dirinya sendiri
        $this->put(route('projects.tasks.update', [$proyek, $b]), [
            'name' => 'B', 'priority' => 'normal', 'parent_id' => $b->id,
            'start_date' => '2026-10-12', 'duration' => 5,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_hapus_induk_menghapus_seluruh_sub_tugas(): void
    {
        $proyek = $this->proyek();
        $this->post(route('projects.tasks.store', $proyek), ['name' => 'A', 'priority' => 'normal'])
            ->assertSessionHasNoErrors();
        $a = $proyek->tasks()->first();
        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak(['name' => 'B', 'parent_id' => $a->id]))
            ->assertSessionHasNoErrors();
        $b = $proyek->tasks()->where('name', 'B')->first();
        $this->post(route('projects.tasks.store', $proyek), $this->dataAnak([
            'name' => 'C', 'parent_id' => $b->id, 'start_date' => '2026-10-19',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(3, $proyek->tasks()->count());

        $this->delete(route('projects.tasks.destroy', [$proyek, $a]))
            ->assertRedirect(route('projects.tasks.index', $proyek));

        $this->assertSame(0, $proyek->tasks()->count());
        $this->assertSame(0, Task::count());
    }
}
