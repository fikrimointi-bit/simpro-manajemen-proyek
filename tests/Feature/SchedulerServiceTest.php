<?php

namespace Tests\Feature;

use App\Exceptions\SchedulingException;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji mesin penjadwalan dependensi (SchedulerService).
 *
 * Fixture: proyek mulai Selasa 30 Juni 2015, weekend Sabtu-Minggu libur,
 * dan hari libur Jumat 17 Juli 2015.
 *
 * Jalankan: php artisan test --filter=SchedulerServiceTest
 */
class SchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    private Project $proyek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = Project::create([
            'name' => 'Proyek Jadwal',
            'start_date' => '2015-06-30', // Selasa
            'weekend_days' => [6, 0],     // Sabtu-Minggu libur
            'weekend_mode' => 'no_work',
        ]);

        $this->proyek->holidays()->create([
            'date' => '2015-07-17', // Jumat libur
            'is_recurring' => false,
        ]);
    }

    /** Buat tugas berjadwal; end_date diisi SchedulerService. */
    private function tugas(string $nama, string $mulai, int $durasi): Task
    {
        return $this->proyek->tasks()->create([
            'name' => $nama,
            'start_date' => $mulai,
            'duration' => $durasi,
        ]);
    }

    /** Pasang dependensi; penyimpanan otomatis menjadwalkan ulang proyek. */
    private function dependensi(Task $pendahulu, Task $penerus, string $tipe = 'FS', int $delay = 0): TaskDependency
    {
        return TaskDependency::create([
            'project_id' => $this->proyek->id,
            'predecessor_id' => $pendahulu->id,
            'successor_id' => $penerus->id,
            'type' => $tipe,
            'lag_days' => $delay,
        ]);
    }

    private function scheduler(): SchedulerService
    {
        return $this->app->make(SchedulerService::class);
    }

    /** Pasangan [mulai, selesai] dalam format YYYY-MM-DD (selalu baca ulang dari DB). */
    private function rentang(Task $task): array
    {
        $task->refresh();

        return [$task->start_date->toDateString(), $task->end_date->toDateString()];
    }

    public function test_tanpa_dependensi_pakai_start_date_sendiri(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);

        $this->scheduler()->scheduleProject($this->proyek);

        // Selasa 30 Jun + 4 hari kerja lagi = Senin 6 Jul
        $this->assertSame(['2015-06-30', '2015-07-06'], $this->rentang($a));
        $this->assertSame(5, $a->duration);
    }

    public function test_tugas_melewati_hari_libur_17_juli(): void
    {
        $g = $this->tugas('G', '2015-07-13', 10);

        $this->scheduler()->scheduleProject($this->proyek);

        // 13-16 Jul (4), 17 Jul libur, 18-19 weekend, 20-24 (9), lalu 27 Jul (ke-10)
        $this->assertSame(['2015-07-13', '2015-07-27'], $this->rentang($g));
    }

    public function test_fs_mulai_hari_kerja_setelah_pendahulu_selesai(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $c = $this->tugas('C', '2015-06-30', 2);
        $c2 = $this->tugas('C2', '2015-06-30', 2);

        // tanpa pemanggilan eksplisit: menyimpan dependensi langsung
        // menjadwalkan ulang proyek lewat event model TaskDependency
        $this->dependensi($a, $c, 'FS', 0);
        $this->dependensi($a, $c2, 'FS', 2);

        // FS+0: mulai hari kerja setelah A selesai (6 Jul) -> 7 Jul
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($c));
        // FS+2: ditambah 2 hari kerja lagi -> mulai 9 Jul
        $this->assertSame(['2015-07-09', '2015-07-10'], $this->rentang($c2));
    }

    public function test_ss_mulai_ditentukan_mulai_pendahulu(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $b = $this->tugas('B', '2015-06-30', 8);

        $this->dependensi($a, $b, 'SS', 3);

        // mulai A (30 Jun) + 3 hari kerja = 3 Jul; 8 hari kerja -> selesai 14 Jul
        $this->assertSame(['2015-07-03', '2015-07-14'], $this->rentang($b));
    }

    public function test_ff_selesai_diikuti_mulai_dihitung_mundur(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $d = $this->tugas('D', '2015-06-30', 3);

        $this->dependensi($a, $d, 'FF', 0);

        // selesai = selesai A (6 Jul), mundur 2 hari kerja -> mulai 2 Jul
        $this->assertSame(['2015-07-02', '2015-07-06'], $this->rentang($d));
    }

    public function test_sf_selesai_ditentukan_mulai_pendahulu(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $e = $this->tugas('E', '2015-06-30', 2);

        $this->dependensi($a, $e, 'SF', 3);

        // selesai = mulai A (30 Jun) + 3 hari kerja = 3 Jul; mundur 1 -> 2 Jul
        $this->assertSame(['2015-07-02', '2015-07-03'], $this->rentang($e));
    }

    public function test_dua_predecessor_mengambil_jadwal_paling_lambat(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $c = $this->tugas('C', '2015-06-30', 2);
        $f = $this->tugas('F', '2015-06-30', 2);

        $this->dependensi($a, $c, 'FS', 0); // C: 7-8 Jul
        $this->dependensi($a, $f, 'FS', 0); // kandidat dari A: mulai 7 Jul
        $this->dependensi($c, $f, 'FS', 0); // kandidat dari C: mulai 9 Jul -> menang

        $this->assertSame(['2015-07-09', '2015-07-10'], $this->rentang($f));
    }

    public function test_siklus_ditolak_sebelum_dependensi_tersimpan(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $b = $this->tugas('B', '2015-06-30', 2);

        $this->dependensi($a, $b, 'FS', 0); // A: 30 Jun-6 Jul, B: 7-8 Jul
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($b));

        try {
            // A->B sudah ada; menambah B->A akan membentuk siklus A->B->A
            $this->dependensi($b, $a, 'FS', 0);
            $this->fail('Seharusnya melempar SchedulingException untuk siklus dependensi.');
        } catch (SchedulingException $e) {
            $this->assertStringContainsString('Siklus', $e->getMessage());
        }

        // perubahan tidak disimpan: baris siklus maupun jadwal tetap seperti semula
        $this->assertSame(1, TaskDependency::count());
        $this->assertSame(['2015-06-30', '2015-07-06'], $this->rentang($a));
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($b));
    }

    public function test_siklus_menghentikan_jadwal_ulang_tanpa_menulis_tanggal(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $b = $this->tugas('B', '2015-06-30', 2);

        $this->dependensi($a, $b, 'FS', 0);
        $sebelumB = $this->rentang($b);

        // sisipkan siklus tanpa event (createQuietly) — meniru data rusak
        TaskDependency::createQuietly([
            'project_id' => $this->proyek->id,
            'predecessor_id' => $b->id,
            'successor_id' => $a->id,
            'type' => 'FS',
            'lag_days' => 0,
        ]);

        try {
            $this->scheduler()->scheduleProject($this->proyek);
            $this->fail('Seharusnya melempar SchedulingException untuk siklus dependensi.');
        } catch (SchedulingException $e) {
            $this->assertStringContainsString('Siklus', $e->getMessage());
        }

        // topological sort gagal SEBELUM menulis: jadwal lama tidak berubah
        $this->assertSame(['2015-06-30', '2015-07-06'], $this->rentang($a));
        $this->assertSame($sebelumB, $this->rentang($b));
    }

    public function test_ubah_durasi_lewat_update_menggeser_tugas_link_akhir(): void
    {
        // susun A beserta turunan lewat keempat tipe link
        $a = $this->tugas('A', '2015-06-30', 5);
        $b = $this->tugas('B', '2015-07-03', 8);
        $c = $this->tugas('C', '2015-06-30', 2);
        $d = $this->tugas('D', '2015-06-30', 3);
        $e = $this->tugas('E', '2015-06-30', 2);

        $this->dependensi($a, $b, 'SS', 3);
        $this->dependensi($a, $c, 'FS', 0);
        $this->dependensi($a, $d, 'FF', 0);
        $this->dependensi($a, $e, 'SF', 3);

        // jadwal awal sesuai skenario
        $this->assertSame(['2015-06-30', '2015-07-06'], $this->rentang($a));
        $this->assertSame(['2015-07-03', '2015-07-14'], $this->rentang($b));
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($c));
        $this->assertSame(['2015-07-02', '2015-07-06'], $this->rentang($d));
        $this->assertSame(['2015-07-02', '2015-07-03'], $this->rentang($e));

        // ubah durasi A 5 -> 8 lewat PUT ke rute update tugas
        $this->put(route('projects.tasks.update', [$this->proyek, $a]), [
            'name' => 'A',
            'priority' => 'normal',
            'start_date' => '2015-06-30',
            'duration' => 8,
        ])
            ->assertRedirect(route('projects.tasks.index', $this->proyek))
            ->assertSessionHasNoErrors();

        // A melebar: 30 Jun - 9 Jul (8 hari kerja)
        $this->assertSame(['2015-06-30', '2015-07-09'], $this->rentang($a));
        // link berbasis SELESAI ikut bergeser...
        $this->assertSame(['2015-07-10', '2015-07-13'], $this->rentang($c)); // FS
        $this->assertSame(['2015-07-07', '2015-07-09'], $this->rentang($d)); // FF
        // ...sedangkan link berjangkar ke MULAI A tidak berubah
        // (SS & SF tidak terpengaruh perubahan durasi pendahulu)
        $this->assertSame(['2015-07-03', '2015-07-14'], $this->rentang($b));
        $this->assertSame(['2015-07-02', '2015-07-03'], $this->rentang($e));
    }

    public function test_ubah_mulai_lewat_update_menggeser_tugas_link_mulai(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $b = $this->tugas('B', '2015-07-03', 8);
        $e = $this->tugas('E', '2015-06-30', 2);

        $this->dependensi($a, $b, 'SS', 3);
        $this->dependensi($a, $e, 'SF', 3);

        $this->assertSame(['2015-07-03', '2015-07-14'], $this->rentang($b));
        $this->assertSame(['2015-07-02', '2015-07-03'], $this->rentang($e));

        // geser mulai A ke Rabu 1 Jul lewat PUT (durasi ikut dikirim, wajib)
        $this->put(route('projects.tasks.update', [$this->proyek, $a]), [
            'name' => 'A',
            'priority' => 'normal',
            'start_date' => '2015-07-01',
            'duration' => 5,
        ])
            ->assertRedirect(route('projects.tasks.index', $this->proyek))
            ->assertSessionHasNoErrors();

        // A: 1-7 Jul; link berjangkar ke mulai A ikut bergeser
        $this->assertSame(['2015-07-01', '2015-07-07'], $this->rentang($a));
        $this->assertSame(['2015-07-06', '2015-07-15'], $this->rentang($b)); // SS+3 -> mulai 6 Jul
        $this->assertSame(['2015-07-03', '2015-07-06'], $this->rentang($e)); // SF+3 -> selesai 6 Jul
    }

    public function test_jadwal_ulang_saat_kalender_berubah(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $c = $this->tugas('C', '2015-06-30', 2);

        $this->dependensi($a, $c, 'FS', 0);

        $this->assertSame(['2015-06-30', '2015-07-06'], $this->rentang($a));
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($c));

        // tambah hari libur 1 Juli lewat rute — memicu jadwal ulang otomatis
        $this->post(route('holidays.store', $this->proyek), [
            'date' => '2015-07-01',
            'type' => 'sekali',
        ])
            ->assertRedirect(route('projects.show', $this->proyek))
            ->assertSessionHasNoErrors();

        // A tetap mulai 30 Jun, selesainya meleset melewati libur (5 hari kerja)
        $this->assertSame(['2015-06-30', '2015-07-07'], $this->rentang($a));
        $this->assertSame(['2015-07-08', '2015-07-09'], $this->rentang($c));
    }

    /**
     * Keputusan desain (ala GanttProject): setelah dependensi dihapus, tanggal
     * tugas TETAP di posisi terakhir — tidak kembali ke start_date awalnya.
     */
    public function test_hapus_dependensi_mempertahankan_tanggal_terakhir(): void
    {
        $a = $this->tugas('A', '2015-06-30', 5);
        $c = $this->tugas('C', '2015-07-01', 2); // jadwal awal sendiri: 1-2 Jul

        $dependensi = $this->dependensi($a, $c, 'FS', 0);
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($c));

        // event deleted tetap memicu scheduleProject, jadi tugas lain yang
        // terhubung ikut dihitung ulang
        $dependensi->delete();

        // C tidak kembali ke 1-2 Jul: jadwal dependensi sudah tersimpan di
        // kolom start_date tugas, sehingga tanpa dependensi pun ia tetap
        // berdiri di posisi terakhirnya (7-8 Jul)
        $this->assertSame(['2015-07-07', '2015-07-08'], $this->rentang($c));
    }
}
