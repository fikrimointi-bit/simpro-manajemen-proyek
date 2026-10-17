<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji penugasan penanggung jawab (tahap 4B): multi-select anggota dari
 * proyek yang sama, tolak anggota proyek lain, badge nama di tabel tugas,
 * peringatan bentrok cuti (berrentang, satu hari, dan berulang) tanpa
 * mengubah jadwal tugas, serta hapus tugas yang ikut membersihkan penugasan.
 * Jalankan: php artisan test --filter=ResourceAssignmentTest
 */
class ResourceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(string $nama = 'Proyek Uji Penugasan'): Project
    {
        return Project::create([
            'name' => $nama,
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    /** Anggota sederhana di proyek tertentu. */
    private function anggota(Project $proyek, string $nama): Resource
    {
        return Resource::create(['project_id' => $proyek->id, 'name' => $nama]);
    }

    /** Data tugas berjadwal: Senin 12 Okt – Jumat 16 Okt 2026. */
    private function dataTugas(array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Tugas Berpenugasan',
            'priority' => 'normal',
            'start_date' => '2026-10-12', // Senin
            'duration' => 5,
        ], $tambahan);
    }

    public function test_simpan_tugas_dengan_banyak_anggota(): void
    {
        $proyek = $this->proyek();
        $a = $this->anggota($proyek, 'Anggota Satu');
        $b = $this->anggota($proyek, 'Anggota Dua');
        $c = $this->anggota($proyek, 'Anggota Tiga');

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$a->id, $b->id, $c->id],
        ]))
            ->assertRedirect(route('projects.tasks.index', $proyek))
            ->assertSessionHasNoErrors();

        $tugas = $proyek->tasks()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            $tugas->resources()->pluck('resources.id')->all()
        );
        $this->assertDatabaseCount('resource_task', 3);
    }

    public function test_edit_tugas_memperbarui_penugasan(): void
    {
        $proyek = $this->proyek();
        $a = $this->anggota($proyek, 'Anggota A');
        $b = $this->anggota($proyek, 'Anggota B');
        $c = $this->anggota($proyek, 'Anggota C');

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$a->id, $b->id, $c->id],
        ]))->assertSessionHasNoErrors();

        $tugas = $proyek->tasks()->firstOrFail();

        // edit: sisa dua anggota, Anggota A dilepas
        $this->put(route('projects.tasks.update', [$proyek, $tugas]), [
            'name' => 'Tugas Berpenugasan',
            'priority' => 'normal',
            'start_date' => '2026-10-12',
            'duration' => 5,
            'resource_ids' => [$b->id, $c->id],
        ])
            ->assertRedirect(route('projects.tasks.index', $proyek))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$b->id, $c->id],
            $tugas->resources()->pluck('resources.id')->all()
        );
        $this->assertDatabaseCount('resource_task', 2);
    }

    public function test_tolak_anggota_dari_proyek_lain(): void
    {
        $proyek = $this->proyek('Proyek Asal');
        $proyekLain = $this->proyek('Proyek Lain');
        $anggotaAsal = $this->anggota($proyek, 'Anggota Asal');
        $anggotaLain = $this->anggota($proyekLain, 'Anggota Lain');

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$anggotaLain->id, $anggotaAsal->id],
        ]))
            ->assertSessionHasErrors('resource_ids.0');

        // validasi gagal total: tugas & penugasan tidak tersimpan sama sekali
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('resource_task', 0);
    }

    public function test_deteksi_bentrok_cuti_di_tabel_tugas(): void
    {
        $proyek = $this->proyek();

        // anggota dengan cuti berrentang 14–15 Okt 2026 (beririsan di tengah)
        $sari = $this->anggota($proyek, 'Sari Wulandari');
        $sari->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-14',
            'end_date' => '2026-10-15',
        ]);

        // anggota dengan cuti satu hari (end_date kosong) tepat di hari terakhir
        $tio = $this->anggota($proyek, 'Tio Prakoso');
        $tio->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-16',
        ]);

        // anggota dengan cuti berulang: tahun cuti 2018 diabaikan, bulan+tanggal
        // 15 Okt jatuh di tengah rentang tugas 12–16 Okt 2026
        $reza = $this->anggota($proyek, 'Reza Mahendra');
        $reza->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2018-10-15',
            'is_recurring' => true,
        ]);

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$sari->id, $tio->id, $reza->id],
        ]))->assertSessionHasNoErrors();

        $tugas = $proyek->tasks()->firstOrFail();
        $mulaiSimpan = $tugas->start_date->toDateString();
        $selesaiSimpan = $tugas->end_date->toDateString();

        // --- tabel tugas: ikon peringatan + nama anggota + tanggal cuti ---
        $this->get(route('projects.tasks.index', $proyek))
            ->assertStatus(200)
            ->assertSee('Penanggung Jawab')
            ->assertSee('Sari Wulandari')
            ->assertSee('Tio Prakoso')
            ->assertSee('Reza Mahendra')
            ->assertSee('bi-exclamation-triangle-fill')
            ->assertSee('Bentrok cuti: Sari Wulandari (14 Okt 2026 s/d 15 Okt 2026)')
            ->assertSee('Bentrok cuti: Tio Prakoso (16 Okt 2026)')
            // cuti berulang ditampilkan tanpa tahun (hanya hari + bulan)
            ->assertSee('Bentrok cuti: Reza Mahendra (15 Okt)');

        // peringatan hanya laporan baca-data: jadwal tugas TIDAK diubah
        $tugas->refresh();
        $this->assertSame($mulaiSimpan, $tugas->start_date->toDateString());
        $this->assertSame($selesaiSimpan, $tugas->end_date->toDateString());
        $this->assertSame(5, $tugas->duration);
    }

    public function test_tanpa_peringatan_bila_cuti_di_luar_rentang(): void
    {
        $proyek = $this->proyek();

        // cuti jauh sebelum tugas (awal Oktober), tidak boleh beri peringatan
        $anggota = $this->anggota($proyek, 'Dewi Anggraini');
        $anggota->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-06',
            'end_date' => '2026-10-07',
        ]);

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$anggota->id],
        ]))->assertSessionHasNoErrors();

        $this->get(route('projects.tasks.index', $proyek))
            ->assertStatus(200)
            ->assertDontSee('Bentrok cuti')
            ->assertSee('Dewi Anggraini'); // badge tetap tampil
    }

    public function test_smoke_get_halaman_tugas(): void
    {
        $proyek = $this->proyek();
        $anggota = $this->anggota($proyek, 'Anggota Smoke');

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$anggota->id],
        ]))->assertSessionHasNoErrors();

        $tugas = $proyek->tasks()->firstOrFail();

        $this->get(route('projects.tasks.index', $proyek))->assertStatus(200);
        $this->get(route('projects.tasks.create', $proyek))->assertStatus(200);

        $this->get(route('projects.tasks.edit', [$proyek, $tugas]))
            ->assertStatus(200)
            ->assertSee('Anggota Smoke'); // daftar anggota ikut tampil di form
    }

    public function test_hapus_tugas_iktu_menghapus_penugasan(): void
    {
        $proyek = $this->proyek();
        $a = $this->anggota($proyek, 'Anggota Hapus A');
        $b = $this->anggota($proyek, 'Anggota Hapus B');

        $this->post(route('projects.tasks.store', $proyek), $this->dataTugas([
            'resource_ids' => [$a->id, $b->id],
        ]))->assertSessionHasNoErrors();

        $tugas = $proyek->tasks()->firstOrFail();
        $this->assertDatabaseCount('resource_task', 2);

        $this->delete(route('projects.tasks.destroy', [$proyek, $tugas]))
            ->assertRedirect(route('projects.tasks.index', $proyek))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('resource_task', 0);
        $this->assertDatabaseCount('tasks', 0);
    }
}