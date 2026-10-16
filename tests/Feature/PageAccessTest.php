<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji akses halaman utama: semua halaman harus merespons 200 (tanpa 500).
 * Menangkap bug kelas seperti Carbon yang tidak ter-import di helper view.
 *
 * Jalankan: php artisan test --filter=PageAccessTest
 */
class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    private Project $proyek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = Project::create([
            'name' => 'Proyek Contoh',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    public function test_beranda_status_200(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_daftar_proyek_status_200(): void
    {
        $this->get(route('projects.index'))->assertStatus(200);
    }

    public function test_form_proyek_baru_status_200(): void
    {
        $this->get(route('projects.create'))->assertStatus(200);
    }

    public function test_halaman_proyek_status_200(): void
    {
        $this->get(route('projects.show', $this->proyek))->assertStatus(200);
    }

    public function test_daftar_tugas_status_200(): void
    {
        $this->get(route('projects.tasks.index', $this->proyek))->assertStatus(200);
    }

    public function test_edit_proyek_status_200(): void
    {
        $this->get(route('projects.edit', $this->proyek))->assertStatus(200);
    }

    public function test_form_tugas_baru_status_200(): void
    {
        $this->get(route('projects.tasks.create', $this->proyek))->assertStatus(200);
    }

    public function test_form_edit_tugas_status_200(): void
    {
        // tugas dibuat lewat POST agar alur penuh (posisi, jadwal) ikut teruji
        $this->post(route('projects.tasks.store', $this->proyek), [
            'name' => 'Tugas Uji Edit',
            'priority' => 'normal',
            'start_date' => '2026-10-13',
            'duration' => 2,
        ])->assertSessionHasNoErrors();

        $tugas = $this->proyek->tasks()->firstOrFail();

        $this->get(route('projects.tasks.edit', [$this->proyek, $tugas]))->assertStatus(200);
    }
}
