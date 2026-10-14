<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CRUD proyek + pengaturan weekend (dipakai halaman detail). */
class ProjectController extends Controller
{
    /** Daftar semua proyek. */
    public function index(): View
    {
        $projects = Project::orderBy('name')->get();

        return view('projects.index', compact('projects'));
    }

    /** Form buat proyek baru. */
    public function create(): View
    {
        return view('projects.create');
    }

    /** Simpan proyek baru. */
    public function store(Request $request): RedirectResponse
    {
        $project = Project::create($this->validasi($request));

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat.');
    }

    /** Detail proyek: info, pengaturan weekend, dan kelola hari libur. */
    public function show(Project $project): View
    {
        $holidays = $project->holidays()->orderBy('date')->get();

        return view('projects.show', compact('project', 'holidays'));
    }

    /** Form edit proyek. */
    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    /** Simpan perubahan proyek. */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validasi($request));

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil diperbarui.');
    }

    /** Hapus proyek beserta turunannya (cascade di database). */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Proyek berhasil dihapus.');
    }

    /** Update pengaturan weekend saja dari halaman detail. */
    public function updateWeekend(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'weekend_days' => ['array'],
            'weekend_days.*' => ['integer', 'min:0', 'max:6'],
            'weekend_mode' => ['required', 'in:no_work,work'],
        ], [], ['weekend_days' => 'hari weekend']);

        $project->update([
            'weekend_days' => array_values(array_map('intval', $data['weekend_days'] ?? [])),
            'weekend_mode' => $data['weekend_mode'],
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Pengaturan weekend berhasil diperbarui.');
    }

    /** Validasi bersama form buat/edit; hasilnya sudah dinormalkan. */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'weekend_days' => ['array'],
            'weekend_days.*' => ['integer', 'min:0', 'max:6'],
            'weekend_mode' => ['required', 'in:no_work,work'],
        ], [], ['weekend_days' => 'hari weekend']);

        // kosongkan kolom opsional yang dikirim string kosong
        $data['organization'] = ($data['organization'] ?? '') ?: null;
        $data['website'] = ($data['website'] ?? '') ?: null;
        $data['description'] = ($data['description'] ?? '') ?: null;
        $data['weekend_days'] = array_values(array_map('intval', $data['weekend_days'] ?? []));

        return $data;
    }
}
