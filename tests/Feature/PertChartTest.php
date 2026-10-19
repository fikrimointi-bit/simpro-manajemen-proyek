<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji diagram PERT gaya Activity-on-Node (tahap 6A):
 * - node hanya berisi tugas TANPA anak + "Mulai" + "Selesai";
 * - layer tiap node (Mulai 0; tugas = 1 + layer terbesar predecessor,
 *   tanpa predecessor = 1; Selesai = layer terbesar + 1);
 * - edge predecessor → tugas dengan tipe & delay; tugas tanpa predecessor
 *   terhubung dari "Mulai", tanpa successor terhubung ke "Selesai";
 * - halaman PERT dan endpoint JSON-nya merespons 200.
 * Jalankan: php artisan test --filter=PertChartTest
 */
class PertChartTest extends TestCase
{
    use RefreshDatabase;

    /** Proyek dengan weekend Sabtu-Minggu, mode no_work. */
    private function proyek(): Project
    {
        return Project::create([
            'name' => 'Proyek Uji PERT',
            'start_date' => '2026-10-05',
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ]);
    }

    /**
     * Susun graf uji: A tanpa predecessor; B & C setelah A (FS);
     * D setelah B & C (FS). Mengembalikan [id per huruf] => id tugas.
     */
    private function grafAbcd(Project $proyek): array
    {
        $buat = fn (string $nama, string $mulai) => Task::create([
            'project_id' => $proyek->id,
            'name' => $nama,
            'start_date' => $mulai,
            'duration' => 2,
            'priority' => 'normal',
        ]);

        $a = $buat('Tugas A', '2026-10-05');
        $b = $buat('Tugas B', '2026-10-07');
        $c = $buat('Tugas C', '2026-10-07');
        $d = $buat('Tugas D', '2026-10-09');

        $link = fn (Task $pred, Task $succ, string $tipe = 'FS', int $delay = 0) => TaskDependency::create([
            'project_id' => $proyek->id,
            'predecessor_id' => $pred->id,
            'successor_id' => $succ->id,
            'type' => $tipe,
            'lag_days' => $delay,
        ]);

        $link($a, $b);          // A → B (FS)
        $link($a, $c);          // A → C (FS)
        $link($b, $d, 'FS', 2); // B → D (FS + delay 2 hari kerja)
        $link($c, $d);          // C → D (FS)

        return ['A' => $a->id, 'B' => $b->id, 'C' => $c->id, 'D' => $d->id];
    }

    public function test_json_node_layer_dan_edge_benar(): void
    {
        $proyek = $this->proyek();
        $id = $this->grafAbcd($proyek);

        $res = $this->get(route('projects.pert.data', $proyek));
        $res->assertStatus(200);
        $json = $res->json();

        // --- node: hanya A, B, C, D + Mulai + Selesai (tugas induk tidak ada) ---
        $node = collect($json['node'])->keyBy('id');
        $this->assertSame(
            ['mulai', $id['A'], $id['B'], $id['C'], $id['D'], 'selesai'],
            $node->keys()->all(),
            'node harus persis Mulai, A, B, C, D, Selesai (urut, tanpa induk)'
        );

        // node Mulai & Selesai: durasi 0, tanpa tanggal
        $this->assertSame('Mulai', $node['mulai']['nama']);
        $this->assertSame(0, $node['mulai']['durasi']);
        $this->assertSame('Selesai', $node['selesai']['nama']);
        $this->assertSame(0, $node['selesai']['durasi']);

        // --- layer: Mulai 0; A 1; B & C 2; D 3; Selesai 4 ---
        $this->assertSame(0, $node['mulai']['layer'], 'layer Mulai = 0');
        $this->assertSame(1, $node[$id['A']]['layer'], 'A tanpa predecessor = 1');
        $this->assertSame(2, $node[$id['B']]['layer'], 'B setelah A = 2');
        $this->assertSame(2, $node[$id['C']]['layer'], 'C setelah A = 2');
        $this->assertSame(3, $node[$id['D']]['layer'], 'D setelah B & C = 3');
        $this->assertSame(4, $node['selesai']['layer'], 'Selesai = layer terbesar + 1');

        // field node tugas: nama, durasi, tanggal ikut terkirim
        $this->assertSame('Tugas A', $node[$id['A']]['nama']);
        $this->assertSame(2, $node[$id['A']]['durasi']);
        $this->assertSame('2026-10-05', $node[$id['A']]['start_date']);
        $this->assertSame('2026-10-06', $node[$id['A']]['end_date']);

        // --- edge: Mulai → A, A → B, A → C, B → D (delay 2), C → D, D → Selesai ---
        $edge = collect($json['edge']);

        $this->assertCount(6, $edge, 'edge: Mulai→A, A→B, A→C, B→D, C→D, D→Selesai');

        $kunci = fn (array $e) => $e['dari'] . '->' . $e['ke'];
        $daftar = $edge->map($kunci)->all();

        $this->assertContains('mulai->' . $id['A'], $daftar, 'A tanpa predecessor → dari Mulai');
        $this->assertContains($id['A'] . '->' . $id['B'], $daftar);
        $this->assertContains($id['A'] . '->' . $id['C'], $daftar);
        $this->assertContains($id['B'] . '->' . $id['D'], $daftar);
        $this->assertContains($id['C'] . '->' . $id['D'], $daftar);
        $this->assertContains($id['D'] . '->selesai', $daftar, 'D tanpa successor → ke Selesai');

        // tipe & delay tiap edge ikut terbawa dari dependensi
        $edgeBKeD = $edge->first(fn (array $e) => $e['dari'] === $id['B'] && $e['ke'] === $id['D']);
        $this->assertSame('FS', $edgeBKeD['tipe']);
        $this->assertSame(2, $edgeBKeD['delay'], 'delay B→D = 2 hari kerja');

        $edgeCKeD = $edge->first(fn (array $e) => $e['dari'] === $id['C'] && $e['ke'] === $id['D']);
        $this->assertSame('FS', $edgeCKeD['tipe']);
        $this->assertSame(0, $edgeCKeD['delay']);

        $edgeMulaiKeA = $edge->first(fn (array $e) => $e['dari'] === 'mulai' && $e['ke'] === $id['A']);
        $this->assertSame('FS', $edgeMulaiKeA['tipe']);
        $this->assertSame(0, $edgeMulaiKeA['delay']);

        $edgeDKeSelesai = $edge->first(fn (array $e) => $e['dari'] === $id['D'] && $e['ke'] === 'selesai');
        $this->assertSame('FS', $edgeDKeSelesai['tipe']);
        $this->assertSame(0, $edgeDKeSelesai['delay']);
    }

    public function test_tugas_induk_tidak_muncul_sebagai_node(): void
    {
        $proyek = $this->proyek();

        // induk tanpa jadwal + satu sub-tugas berjadwal
        $induk = Task::create([
            'project_id' => $proyek->id,
            'name' => 'Induk PERT',
            'priority' => 'normal',
        ]);
        $anak = Task::create([
            'project_id' => $proyek->id,
            'parent_id' => $induk->id,
            'name' => 'Anak PERT',
            'start_date' => '2026-10-05',
            'duration' => 2,
            'priority' => 'normal',
        ]);

        $node = collect($this->get(route('projects.pert.data', $proyek))->json('node'));

        $this->assertSame(
            [],
            $node->where('nama', 'Induk PERT')->all(),
            'tugas induk tidak boleh menjadi node PERT'
        );
        $this->assertNotContains($induk->id, $node->pluck('id')->all());

        // sub-tugas (tanpa anak) tetap tampil sebagai node
        $nodeAnak = $node->firstWhere('nama', 'Anak PERT');
        $this->assertNotNull($nodeAnak, 'sub-tugas tanpa anak tetap jadi node');
        $this->assertSame(1, $nodeAnak['layer']);
    }

    public function test_halaman_pert_dan_json_status_200(): void
    {
        $proyek = $this->proyek();
        $this->grafAbcd($proyek);

        $this->get(route('projects.pert.index', $proyek))
            ->assertStatus(200)
            ->assertSee('Diagram PERT')
            ->assertSee('Diagram akan tampil di sini');

        $this->get(route('projects.pert.data', $proyek))
            ->assertStatus(200)
            ->assertJsonStructure([
                'proyek' => ['id', 'nama'],
                'node' => [['id', 'nama', 'durasi', 'start_date', 'end_date', 'layer']],
                'edge' => [['dari', 'ke', 'tipe', 'delay']],
            ]);
    }
}
