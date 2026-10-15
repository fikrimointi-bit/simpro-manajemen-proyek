<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Project;
use App\Services\SchedulerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kelola hari libur proyek dari halaman detail proyek. */
class HolidayController extends Controller
{
    public function __construct(private SchedulerService $scheduler)
    {
    }

    /** Tambah hari libur (sekali pakai atau berulang tiap tahun). */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'date' => [
                'required',
                'date',
                // cegah duplikat tanggal yang sama dalam satu proyek
                Rule::unique('holidays', 'date')->where('project_id', $project->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:sekali,berulang'],
        ], [], ['date' => 'tanggal', 'type' => 'jenis libur']);

        $project->holidays()->create([
            'date' => $data['date'],
            'description' => ($data['description'] ?? '') ?: null,
            'is_recurring' => $data['type'] === 'berulang',
        ]);

        // kalender berubah -> jadwalkan ulang seluruh proyek
        $this->scheduler->scheduleProject($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /** Hapus satu hari libur. */
    public function destroy(Project $project, Holiday $holiday): RedirectResponse
    {
        // pastikan libur memang milik proyek di URL
        abort_unless($holiday->project_id === $project->id, 404);

        $holiday->delete();

        // kalender berubah -> jadwalkan ulang seluruh proyek
        $this->scheduler->scheduleProject($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Hari libur berhasil dihapus.');
    }
}
