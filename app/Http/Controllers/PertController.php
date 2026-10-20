<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\CriticalPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * PERT gaya Activity-on-Node (tahap 6A).
 *
 * - Node = hanya tugas TANPA anak, plus node "Mulai" (layer 0) dan "Selesai".
 * - Edge = dependensi predecessor → tugas (dengan tipe & delay);
 *   tugas tanpa predecessor terhubung dari "Mulai",
 *   tugas tanpa successor terhubung ke "Selesai".
 * - Layer = posisi kolom: Mulai 0; tugas = 1 + layer terbesar predecessornya
 *   (tanpa predecessor = 1); Selesai = layer terbesar + 1.
 */
class PertController extends Controller
{
    /** Halaman PERT: kerangka judul + area diagram (diisi pada tahap berikutnya). */
    public function index(Project $project): View
    {
        return view('pert.index', [
            'project' => $project,
        ]);
    }

    /** Endpoint JSON diagram PERT untuk sebuah proyek. */
    public function data(Project $project, CriticalPathService $jalurKritis): JsonResponse
    {
        $semuaTugas = $project->tasks()->orderBy('id')->get();
        $anakPerInduk = $semuaTugas->groupBy(fn (Task $t) => $t->parent_id);

        // node: hanya tugas tanpa anak
        $daftarTugas = $semuaTugas
            ->filter(fn (Task $t) => ! $anakPerInduk->has($t->id))
            ->values();
        $idNodeTugas = $daftarTugas->pluck('id')->all();

        $dependensi = $project->dependencies()->orderBy('id')->get();

        // layer per tugas: 1 + layer terbesar predecessor (rekrusif, hasil di-cache)
        $layerPerTugas = [];
        $hitungLayer = function (Task $t) use (&$hitungLayer, &$layerPerTugas, $dependensi, $semuaTugas): int {
            if (isset($layerPerTugas[$t->id])) {
                return $layerPerTugas[$t->id];
            }

            $predecessorIds = $dependensi
                ->where('successor_id', $t->id)
                ->pluck('predecessor_id');

            if ($predecessorIds->isEmpty()) {
                return $layerPerTugas[$t->id] = 1;
            }

            $terbesar = 0;
            foreach ($predecessorIds as $predecessorId) {
                $predecessor = $semuaTugas->firstWhere('id', $predecessorId);
                if ($predecessor) {
                    $terbesar = max($terbesar, $hitungLayer($predecessor));
                }
            }

            return $layerPerTugas[$t->id] = 1 + $terbesar;
        };

        $node = [
            [
                'id' => 'mulai',
                'nama' => 'Mulai',
                'durasi' => 0,
                'start_date' => null,
                'end_date' => null,
                'layer' => 0,
            ],
        ];

        foreach ($daftarTugas as $t) {
            $node[] = [
                'id' => $t->id,
                'nama' => $t->name,
                'durasi' => $t->duration,
                'start_date' => $t->start_date?->toDateString(),
                'end_date' => $t->end_date?->toDateString(),
                'layer' => $hitungLayer($t),
            ];
        }

        $layerTerbesar = collect($node)->max('layer');

        $node[] = [
            'id' => 'selesai',
            'nama' => 'Selesai',
            'durasi' => 0,
            'start_date' => null,
            'end_date' => null,
            'layer' => $layerTerbesar + 1,
        ];

        // edge: hanya yang ujung-ujungnya node (keduanya tugas tanpa anak)
        $edge = [];

        foreach ($dependensi as $d) {
            if (in_array($d->predecessor_id, $idNodeTugas, true)
                && in_array($d->successor_id, $idNodeTugas, true)) {
                $edge[] = [
                    'dari' => $d->predecessor_id,
                    'ke' => $d->successor_id,
                    'tipe' => $d->type,
                    'delay' => $d->lag_days,
                ];
            }
        }

        // tugas tanpa predecessor (di antara node) -> dari "Mulai";
        // tugas tanpa successor (di antara node) -> ke "Selesai"
        foreach ($daftarTugas as $t) {
            $adaPredecessor = $dependensi
                ->where('successor_id', $t->id)
                ->contains(fn (TaskDependency $d) => in_array($d->predecessor_id, $idNodeTugas, true));
            $adaSuccessor = $dependensi
                ->where('predecessor_id', $t->id)
                ->contains(fn (TaskDependency $d) => in_array($d->successor_id, $idNodeTugas, true));

            if (! $adaPredecessor) {
                $edge[] = ['dari' => 'mulai', 'ke' => $t->id, 'tipe' => 'FS', 'delay' => 0];
            }
            if (! $adaSuccessor) {
                $edge[] = ['dari' => $t->id, 'ke' => 'selesai', 'tipe' => 'FS', 'delay' => 0];
            }
        }

        // --- jalur kritis (tahap 7B): id kritis + edge antar tugas kritis ---
        $jalurKritisAnalisis = $jalurKritis->hitung($project);

        $edgeKritis = [];
        foreach ($jalurKritisAnalisis['jalur_kritis'] as $jalur) {
            $rute = $jalur['rute'];
            for ($i = 0; $i < count($rute) - 1; $i++) {
                $pasangan = ['dari' => $rute[$i], 'ke' => $rute[$i + 1]];
                if (! in_array($pasangan, $edgeKritis, true)) {
                    $edgeKritis[] = $pasangan;
                }
            }
        }

        return response()->json([
            'proyek' => [
                'id' => $project->id,
                'nama' => $project->name,
            ],
            'node' => $node,
            'edge' => $edge,
            // analisis jalur kritis (tahap 7B): id tugas kritis + edge antar
            // tugas kritis (pasangan berurutan pada jalur kritis, unik)
            'tugas_kritis' => $jalurKritisAnalisis['tugas_kritis'],
            'edge_kritis' => $edgeKritis,
        ]);
    }
}
