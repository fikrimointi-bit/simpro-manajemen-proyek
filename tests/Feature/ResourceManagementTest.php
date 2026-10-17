<?php

namespace Tests\Feature;

use App\Http\Controllers\ResourceController;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceDayOff;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Uji sumber daya (tahap 4A): CRUD anggota lewat form POST, data kustom
 * JSON, cuti pribadi berrentang, dan hapus anggota yang ikut membersihkan
 * cuti + penugasannya.
 *
 * Jalankan: php artisan test --filter=ResourceManagementTest
 */
class ResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(): Project
    {
        return Project::create([
            'name' => 'Proyek Uji Sumber Daya',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    /** Data anggota valid beserta satu baris data kustom. */
    private function dataAnggota(array $tambahan = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'role' => 'Koordinator Lapangan',
            'salary' => 3500000,
            'custom_fields' => [
                ['label' => 'NIP', 'value' => '19980101'],
            ],
        ], $tambahan);
    }

    public function test_crud_anggota_lewat_form(): void
    {
        $proyek = $this->proyek();

        // --- CREATE: POST tanpa error, data tersimpan termasuk JSON kustom ---
        $this->post(route('projects.resources.store', $proyek), $this->dataAnggota())
            ->assertSessionHasNoErrors();

        $anggota = Resource::firstOrFail();
        $this->assertSame('Budi Santoso', $anggota->name);
        $this->assertSame('081234567890', $anggota->phone);
        $this->assertSame('budi@example.com', $anggota->email);
        $this->assertSame('Koordinator Lapangan', $anggota->role);
        $this->assertSame(3500000, $anggota->salary);
        $this->assertSame([['label' => 'NIP', 'value' => '19980101']], $anggota->custom_fields);

        // --- READ: tabel menampilkan format Rupiah ---
        $this->get(route('projects.resources.index', $proyek))
            ->assertStatus(200)
            ->assertSee('Budi Santoso')
            ->assertSee('Rp 3.500.000');

        // detail anggota menampilkan data kustom
        $this->get(route('projects.resources.show', [$proyek, $anggota]))
            ->assertStatus(200)
            ->assertSee('NIP')
            ->assertSee('19980101');

        // form edit tampil (data ter-populasi)
        $this->get(route('projects.resources.edit', [$proyek, $anggota]))
            ->assertStatus(200)
            ->assertSee('Budi Santoso');

        // --- UPDATE: ganti peran & gaji lewat PUT ---
        $this->put(route('projects.resources.update', [$proyek, $anggota]), $this->dataAnggota([
            'role' => 'Manajer Proyek',
            'salary' => 5000000,
            'custom_fields' => [
                ['label' => 'NIP', 'value' => '19980101'],
                ['label' => 'Divisi', 'value' => 'Operasional'],
            ],
        ]))
            ->assertSessionHasNoErrors();

        $anggota->refresh();
        $this->assertSame('Manajer Proyek', $anggota->role);
        $this->assertSame(5000000, $anggota->salary);
        $this->assertCount(2, $anggota->custom_fields);
        $this->get(route('projects.resources.index', $proyek))
            ->assertSee('Rp 5.000.000');
    }

    public function test_validasi_data_kustom_dan_gaji(): void
    {
        $proyek = $this->proyek();

        // nama kolom kosong tapi nilai terisi -> error di field baris itu
        $this->post(route('projects.resources.store', $proyek), $this->dataAnggota([
            'custom_fields' => [
                ['label' => '', 'value' => 'Seharusnya gagal'],
            ],
        ]))
            ->assertSessionHasErrors('custom_fields.0.label');

        // nama kolom duplikat -> ditolak (distinct)
        $this->post(route('projects.resources.store', $proyek), $this->dataAnggota([
            'custom_fields' => [
                ['label' => 'NIP', 'value' => 'A'],
                ['label' => 'NIP', 'value' => 'B'],
            ],
        ]))
            ->assertSessionHasErrors('custom_fields.1.label');

        // gaji negatif -> ditolak
        $this->post(route('projects.resources.store', $proyek), $this->dataAnggota([
            'salary' => -1000,
        ]))
            ->assertSessionHasErrors('salary');

        $this->assertSame(0, Resource::count());

        // baris kustom yang kosong total dibuang (tanpa error) — tetap lolos
        $this->post(route('projects.resources.store', $proyek), $this->dataAnggota([
            'custom_fields' => [
                ['label' => 'Divisi', 'value' => 'Operasional'],
                ['label' => '', 'value' => ''],
            ],
        ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [['label' => 'Divisi', 'value' => 'Operasional']],
            Resource::firstOrFail()->custom_fields
        );
    }

    public function test_cuti_pribadi_berrentang(): void
    {
        $proyek = $this->proyek();
        $anggota = Resource::create([
            'project_id' => $proyek->id,
            'name' => 'Siti Aminah',
        ]);

        // rentang valid: sampai > dari
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-20',
            'end_date' => '2026-10-22',
            'description' => 'Cuti keluarga',
        ])
            ->assertSessionHasNoErrors();

        $cuti = ResourceDayOff::firstOrFail();
        $this->assertSame('2026-10-20', $cuti->date->toDateString());
        $this->assertSame('2026-10-22', $cuti->end_date->toDateString());
        $this->assertSame('Cuti keluarga', $cuti->description);
        $this->assertSame($proyek->id, $cuti->project_id);

        // detail anggota menampilkan rentang cuti
        $this->get(route('projects.resources.show', [$proyek, $anggota]))
            ->assertStatus(200)
            ->assertSee('Cuti keluarga');

        // sampai < dari -> ditolak, data tidak berubah
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-25',
            'end_date' => '2026-10-24',
        ])
            ->assertSessionHasErrors('end_date');
        $this->assertSame(1, ResourceDayOff::count());

        // tanggal mulai sama dua kali -> ditolak (unik per anggota)
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-20',
            'end_date' => '2026-10-21',
        ])
            ->assertSessionHasErrors('date');
        $this->assertSame(1, ResourceDayOff::count());

        // rentang sama dengan tanggal mulai (dari == sampai) -> boleh
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-11-01',
            'end_date' => '2026-11-01',
        ])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ResourceDayOff::count());

        // hapus satu cuti
        $this->delete(route('resource-days-off.destroy', [$proyek, $anggota, $cuti]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ResourceDayOff::count());
    }

    public function test_cuti_irisan_sebagian_ditolak(): void
    {
        $proyek = $this->proyek();
        $anggota = Resource::create([
            'project_id' => $proyek->id,
            'name' => 'Rina Marlina',
        ]);

        // cuti lama: 20–22 Oktober
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-20',
            'end_date' => '2026-10-22',
            'description' => 'Cuti lama',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ResourceDayOff::count());

        // irisan di tengah (21–23 masuk ke dalam 20–22) -> ditolak,
        // pesan berbahasa Indonesia muncul di field date, data tidak berubah
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-21',
            'end_date' => '2026-10-23',
        ])
            ->assertSessionHasErrors([
                'date' => 'Rentang tanggal bertabrakan dengan cuti lain anggota ini.',
            ]);
        $this->assertSame(1, ResourceDayOff::count());

        // irisan di batas: berakhir tepat pada hari mulai cuti lama -> ditolak
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-18',
            'end_date' => '2026-10-20',
        ])->assertSessionHasErrors('date');
        $this->assertSame(1, ResourceDayOff::count());

        // bersebelahan tapi tidak beririsan (mulai 23, cuti lama berakhir 22) -> lolos
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-10-23',
            'end_date' => '2026-10-24',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, ResourceDayOff::count());

        // cuti lama tanpa end_date: dianggap sama dengan date-nya (coalesce)
        $anggota->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-11-05',
            'end_date' => null,
        ]);
        $this->assertSame(3, ResourceDayOff::count());

        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-11-05',
            'end_date' => '2026-11-06',
        ])->assertSessionHasErrors('date');
        $this->assertSame(3, ResourceDayOff::count());

        // hari setelahnya (6–7 Nov) tidak menyentuh cuti 5 Nov -> lolos
        $this->post(route('resource-days-off.store', [$proyek, $anggota]), [
            'date' => '2026-11-06',
            'end_date' => '2026-11-07',
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, ResourceDayOff::count());
    }

    public function test_edit_cuti_tidak_bentrok_dengan_dirinya_sendiri(): void
    {
        // Saat ini belum ada endpoint edit cuti (hanya store & destroy), jadi
        // aturan "abaikan cuti yang sedang diedit" diuji langsung dari controller
        // lewat pemanggilan rentangTabrak dengan $cutiDiabaikan.
        $proyek = $this->proyek();
        $anggota = Resource::create([
            'project_id' => $proyek->id,
            'name' => 'Dewi Sartika',
        ]);

        $cuti = $anggota->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-20',
            'end_date' => '2026-10-22',
        ]);

        $cek = new ReflectionMethod(ResourceController::class, 'rentangTabrak');

        // tanpa mengabaikan apa pun, rentang yang sama dianggap irisan
        $this->assertTrue(
            $cek->invoke(new ResourceController(), $anggota, '2026-10-21', '2026-10-22')
        );

        // saat edit: cuti yang sedang diedit diabaikan -> tidak dianggap bentrok
        $this->assertFalse(
            $cek->invoke(new ResourceController(), $anggota, '2026-10-21', '2026-10-22', $cuti->id)
        );

        // tetap dianggap irisan terhadap cuti anggota LAIN (bukan dirinya sendiri)
        $anggotaLain = Resource::create([
            'project_id' => $proyek->id,
            'name' => 'Anggota Lain',
        ]);
        $anggotaLain->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-21',
            'end_date' => '2026-10-25',
        ]);

        $this->assertTrue(
            $cek->invoke(new ResourceController(), $anggotaLain, '2026-10-20', '2026-10-21', $cuti->id)
        );
    }

    public function test_hapus_anggota_mengikutsertakan_cuti_dan_penugasan(): void
    {
        $proyek = $this->proyek();
        $anggota = Resource::create([
            'project_id' => $proyek->id,
            'name' => 'Andi Wijaya',
        ]);

        $anggota->daysOff()->create([
            'project_id' => $proyek->id,
            'date' => '2026-10-20',
            'end_date' => '2026-10-21',
        ]);

        // penugasan: alokasi anggota ke satu tugas (pivot resource_task)
        $tugas = Task::create([
            'project_id' => $proyek->id,
            'name' => 'Tugas Penugasan',
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-13',
            'duration' => 2,
        ]);
        $anggota->tasks()->attach($tugas, ['effort' => 8]);

        $this->assertSame(1, ResourceDayOff::count());
        $this->assertSame(1, $anggota->tasks()->count());

        // hapus anggota -> cuti & penugasan ikut hilang, tugas tetap ada
        $this->delete(route('projects.resources.destroy', [$proyek, $anggota]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Resource::count());
        $this->assertSame(0, ResourceDayOff::count());
        $this->assertSame(0, $tugas->resources()->count());
        $this->assertSame(1, Task::count());
    }

    public function test_halaman_sumber_daya_status_200(): void
    {
        $proyek = $this->proyek();
        $anggota = Resource::create(['project_id' => $proyek->id, 'name' => 'Anggota Uji']);

        $this->get(route('projects.resources.index', $proyek))->assertStatus(200);
        $this->get(route('projects.resources.create', $proyek))->assertStatus(200);
        $this->get(route('projects.resources.show', [$proyek, $anggota]))->assertStatus(200);
        $this->get(route('projects.resources.edit', [$proyek, $anggota]))->assertStatus(200);
    }
}
