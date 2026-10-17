<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Chart Gantt dasar (tahap 5A): halaman SVG + JavaScript tanpa library eksternal.
 * Data diambil halaman lewat endpoint JSON terpisah (method data()).
 */
class GanttController extends Controller
{
    /** Halaman Gantt: daftar tugas kiri + timeline SVG kanan (di-render JS). */
    public function index(Project $project): View
    {
        return view('gantt.index', [
            'project' => $project,
        ]);
    }

    /** Endpoint JSON: tugas, dependensi, weekend, dan hari libur (termasuk berulang). */
    public function data(Project $project): JsonResponse
    {
        $tugas = $project->tasks()
            ->with('resources:id,name,color')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $t): array => [
                'id' => $t->id,
                'nama' => $t->name,
                'parent_id' => $t->parent_id,
                'start_date' => $t->start_date?->toDateString(),
                'end_date' => $t->end_date?->toDateString(),
                'color' => $t->color,
                'anggota' => $t->resources
                    ->map(fn (Resource $r): array => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'color' => $r->color,
                    ])
                    ->values()
                    ->all(),
            ]);

        $dependensi = $project->dependencies()
            ->orderBy('id')
            ->get()
            ->map(fn (TaskDependency $d): array => [
                'id' => $d->id,
                'task_id' => $d->successor_id, // tugas penerus (setara "task_id" skema rencana)
                'predecessor_id' => $d->predecessor_id,
                'tipe' => $d->type,            // FS, SS, FF, atau SF
                'delay' => $d->lag_days,       // jeda hari kerja (integer cast)
            ]);

        $hariLibur = $project->holidays()
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $h): array => [
                'date' => $h->date?->toDateString(),
                'description' => $h->description,
                'is_recurring' => $h->is_recurring,
            ]);

        return response()->json([
            'proyek' => [
                'id' => $project->id,
                'nama' => $project->name,
                'start_date' => $project->start_date?->toDateString(),
            ],
            'weekend_days' => $project->weekend_days,
            'weekend_mode' => $project->weekend_mode,
            'tugas' => $tugas,
            'dependensi' => $dependensi,
            'hari_libur' => $hariLibur,
        ]);
    }
}