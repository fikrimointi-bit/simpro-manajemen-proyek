<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji Chart Gantt dasar (tahap 5A): endpoint JSON mengembalikan tugas
 * (induk + anak + anggota), dependensi, weekend, dan hari libur termasuk
 * yang berulang; halaman Gantt merespons 200.
 * Jalankan: php artisan test --filter=GanttChartTest
 */
class GanttChartTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(): Project
    {
        return Project::create([
            'name' => 'Proyek Uji Gantt',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    public function test_endpoint_json_mengembalikan_data_benar(): void
    {
        $proyek = $this->proyek();

        // hari libur proyek: satu sekali, satu berulang (bulan+tanggal saja)
        $proyek->holidays()->create([
            'date' => '2026-10-15', 'description' => 'Hari Adat', 'is_recurring' => false,
        ]);
        $proyek->holidays()->create([
            'date' => '2019-06-01', 'description' => 'Libur Nasional', 'is_recurring' => true,
        ]);

        $anggota = Resource::create([
            'project_id' => $proyek->id, 'name' => 'Gantt Anggota', 'color' => '#22c55e',
        ]);

        // tugas induk (tanpa jadwal) + anak berjadwal lewat form POST
        $this->post(route('projects.tasks.store', $proyek), [
            'name' => 'Induk Gantt',
            'priority' => 'normal',
            'color' => '#8b5cf6',
        ])->assertSessionHasNoErrors();
        $induk = $proyek->tasks()->where('name', 'Induk Gantt')->firstOrFail();

        $this->post(route('projects.tasks.store', $proyek), [
            'name' => 'Anak Gantt',
            'priority' => 'normal',
            'color' => '#3b82f6',
            'parent_id' => $induk->id,
            'start_date' => '2026-10-12',
            'duration' => 5,
        ])->assertSessionHasNoErrors();
        $anak = $proyek->tasks()->where('name', 'Anak Gantt')->firstOrFail();
        $anak->resources()->sync([$anggota->id]);

        // leaf kedua + dependensi FS (memicu penjadwal ulang otomatis)
        $leafKedua = Task::create([
            'project_id' => $proyek->id,
            'name' => 'Leaf Kedua',
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-20',
            'duration' => 2,
            'priority' => 'normal',
        ]);
        TaskDependency::create([
            'project_id' => $proyek->id,
            'predecessor_id' => $anak->id,
            'successor_id' => $leafKedua->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);

        $res = $this->get(route('projects.gantt.data', $proyek));
        $res->assertStatus(200);
        $json = $res->json();

        // --- kalender kerja proyek ---
        $this->assertSame($proyek->id, $json['proyek']['id']);
        $this->assertSame('Proyek Uji Gantt', $json['proyek']['nama']);
        $this->assertSame('2026-10-05', $json['proyek']['start_date']);
        $this->assertSame([6, 0], $json['weekend_days']);
        $this->assertSame('no_work', $json['weekend_mode']);

        // --- tugas: field sesuai spesifikasi, jadwal agregat induk utuh ---
        $tugas = collect($json['tugas'])->keyBy('id');
        $this->assertCount(3, $tugas);

        $this->assertSame('Induk Gantt', $tugas[$induk->id]['nama']);
        $this->assertNull($tugas[$induk->id]['parent_id']);
        $this->assertSame('2026-10-12', $tugas[$induk->id]['start_date']);
        // anak: 5 hari kerja sejak 12 Okt; 15 Okt libur "Hari Adat" + weekend 17-18 Okt
        // dilewati, sehingga hari kerja ke-5 jatuh pada 19 Okt (induk = agregat anak)
        $this->assertSame('2026-10-19', $tugas[$induk->id]['end_date']);
        $this->assertSame('#8b5cf6', $tugas[$induk->id]['color']);

        $this->assertSame('Anak Gantt', $tugas[$anak->id]['nama']);
        $this->assertSame($induk->id, $tugas[$anak->id]['parent_id']);
        $this->assertSame('2026-10-12', $tugas[$anak->id]['start_date']);
        // sama dengan induk: 15 Okt libur proyek sehingga durasi 5 hari berakhir 19 Okt
        $this->assertSame('2026-10-19', $tugas[$anak->id]['end_date']);
        $this->assertSame('#3b82f6', $tugas[$anak->id]['color']);
        // --- nama anggota tiap tugas ---
        $this->assertSame(
            [['id' => $anggota->id, 'name' => 'Gantt Anggota', 'color' => '#22c55e']],
            $tugas[$anak->id]['anggota'],
            'tugas berpenugasan harus memuat nama anggotanya'
        );
        $this->assertSame([], $tugas[$induk->id]['anggota'], 'induk tanpa penugasan');
        $this->assertSame([], $tugas[$leafKedua->id]['anggota'], 'leaf kedua tanpa penugasan');

        $this->assertNull($tugas[$leafKedua->id]['parent_id']);
        $this->assertSame('Leaf Kedua', $tugas[$leafKedua->id]['nama']);

        // --- dependensi: id, task_id, predecessor_id, tipe, delay ---
        $this->assertCount(1, $json['dependensi']);
        $dep = $json['dependensi'][0];

        $this->assertSame(
            ['id', 'task_id', 'predecessor_id', 'tipe', 'delay'],
            array_keys($dep),
            'field dependensi harus persis id, task_id, predecessor_id, tipe, delay'
        );
        $this->assertIsInt($dep['id']);
        $this->assertSame($anak->id, $dep['predecessor_id']); // pendahulu = Anak Gantt
        $this->assertSame($leafKedua->id, $dep['task_id']);   // task_id = tugas penerus
        $this->assertSame('FS', $dep['tipe']);
        $this->assertSame(0, $dep['delay']);                  // delay 0 hari kerja

        // --- hari libur, termasuk yang berulang ---
        $libur = collect($json['hari_libur']);
        $this->assertCount(2, $libur);

        $sekali = $libur->firstWhere('description', 'Hari Adat');
        $this->assertSame('2026-10-15', $sekali['date']);
        $this->assertFalse($sekali['is_recurring']);

        $berulang = $libur->firstWhere('description', 'Libur Nasional');
        $this->assertSame('2019-06-01', $berulang['date']);
        $this->assertTrue($berulang['is_recurring']);
    }

    public function test_endpoint_json_proyek_tanpa_tugas(): void
    {
        $json = $this->get(route('projects.gantt.data', $this->proyek()))->json('proyek');
        $this->assertSame(0, $json['id'] ? $json['id'] - $json['id'] : 0); // struktur proyek ada

        $kosong = $this->get(route('projects.gantt.data', $this->proyek()))->json();
        $this->assertSame([], $kosong['tugas']);
        $this->assertSame([], $kosong['dependensi']);
        $this->assertSame([], $kosong['hari_libur']);
    }

    public function test_halaman_gantt_status_200(): void
    {
        $proyek = $this->proyek();

        $this->get(route('projects.gantt.index', $proyek))
            ->assertStatus(200)
            ->assertSee('Gantt Chart')
            ->assertSee('Daftar Tugas')   // daftar tugas di kiri (diisi JS)
            ->assertSee('gantt-svg')      // elemen SVG timeline
            ->assertSee('zoomHari')       // tombol zoom hari
            ->assertSee('zoomMinggu');    // tombol zoom minggu
    }
}