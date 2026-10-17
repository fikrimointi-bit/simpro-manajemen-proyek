<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceDayOff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD sumber daya (anggota tim) per proyek + cuti pribadi berrentang.
 * Tahap 4A: SchedulerService & fitur lama tidak disentuh — cuti anggota
 * belum memengaruhi penjadwalan.
 */
class ResourceController extends Controller
{
    /** Daftar anggota tim proyek. */
    public function index(Project $project): View
    {
        $anggota = $project->resources()->orderBy('name')->get();

        return view('resources.index', compact('project', 'anggota'));
    }

    /** Form tambah anggota. */
    public function create(Project $project): View
    {
        return view('resources.create', compact('project'));
    }

    /** Simpan anggota baru. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $anggota = $project->resources()->create($this->validasi($request));

        return redirect()
            ->route('projects.resources.show', [$project, $anggota])
            ->with('success', 'Anggota berhasil ditambahkan.');
    }

    /** Detail anggota: data pribadi, data kustom, dan cuti pribadi. */
    public function show(Project $project, Resource $resource): View
    {
        $this->pastikanMilik($project, $resource);

        return view('resources.show', [
            'project' => $project,
            'anggota' => $resource,
            'cuti' => $resource->daysOff()->orderBy('date')->get(),
        ]);
    }

    /** Form edit anggota. */
    public function edit(Project $project, Resource $resource): View
    {
        $this->pastikanMilik($project, $resource);

        return view('resources.edit', compact('project', 'resource'));
    }

    /** Simpan perubahan anggota. */
    public function update(Request $request, Project $project, Resource $resource): RedirectResponse
    {
        $this->pastikanMilik($project, $resource);

        $resource->update($this->validasi($request));

        return redirect()
            ->route('projects.resources.show', [$project, $resource])
            ->with('success', 'Anggota berhasil diperbarui.');
    }

    /** Hapus anggota; cuti & penugasannya ikut terhapus. */
    public function destroy(Project $project, Resource $resource): RedirectResponse
    {
        $this->pastikanMilik($project, $resource);

        // hapus eksplisit lebih dulu — tetap aman walau FK cascade tidak aktif
        $resource->daysOff()->delete();
        $resource->tasks()->detach();
        $resource->delete();

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('success', 'Anggota beserta cuti dan penugasannya berhasil dihapus.');
    }

    /** Tambah cuti pribadi (rentang tanggal dari – sampai). */
    public function storeDayOff(Request $request, Project $project, Resource $resource): RedirectResponse
    {
        $this->pastikanMilik($project, $resource);

        $data = $request->validate([
            // cegah 500 dari unique (resource_id, date) di database
            'date' => [
                'required',
                'date',
                Rule::unique('resource_days_off', 'date')->where('resource_id', $resource->id),
            ],
            'end_date' => ['required', 'date', 'after_or_equal:date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], [
            'date' => 'tanggal dari',
            'end_date' => 'tanggal sampai',
            'description' => 'keterangan',
        ]);

        $resource->daysOff()->create([
            'project_id' => $project->id,
            'date' => $data['date'],
            'end_date' => $data['end_date'],
            'description' => ($data['description'] ?? '') ?: null,
        ]);

        return redirect()
            ->route('projects.resources.show', [$project, $resource])
            ->with('success', 'Cuti pribadi berhasil ditambahkan.');
    }

    /** Hapus satu cuti pribadi. */
    public function destroyDayOff(Project $project, Resource $resource, ResourceDayOff $dayOff): RedirectResponse
    {
        $this->pastikanMilik($project, $resource);

        // pastikan cuti memang milik anggota di URL
        abort_unless($dayOff->resource_id === $resource->id, 404);

        $dayOff->delete();

        return redirect()
            ->route('projects.resources.show', [$project, $resource])
            ->with('success', 'Cuti pribadi berhasil dihapus.');
    }

    /** Validasi bersama form buat/edit; hasilnya sudah dinormalkan. */
    private function validasi(Request $request): array
    {
        // buang baris data kustom yang kosong total (belum diisi pengguna)
        $mentah = $request->input('custom_fields');
        if (is_array($mentah)) {
            $request->merge(['custom_fields' => array_values(array_filter(
                $mentah,
                fn ($baris): bool => trim((string) ($baris['label'] ?? '')) !== ''
                    || trim((string) ($baris['value'] ?? '')) !== ''
            ))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'salary' => ['nullable', 'integer', 'min:0'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.label' => ['required', 'string', 'max:100', 'distinct'],
            'custom_fields.*.value' => ['required', 'string', 'max:255'],
        ], [], [
            'name' => 'nama',
            'phone' => 'telepon',
            'email' => 'email',
            'role' => 'peran',
            'salary' => 'gaji',
            'custom_fields.*.label' => 'nama kolom',
            'custom_fields.*.value' => 'nilai',
        ]);

        // kosongkan kolom opsional yang dikirim string kosong
        $data['phone'] = ($data['phone'] ?? '') ?: null;
        $data['email'] = ($data['email'] ?? '') ?: null;
        $data['role'] = ($data['role'] ?? '') ?: null;
        $data['salary'] = isset($data['salary']) && $data['salary'] !== '' ? (int) $data['salary'] : null;
        $data['custom_fields'] = $data['custom_fields'] ?? [];

        return $data;
    }

    /** Tolak akses anggota yang bukan milik proyek di URL. */
    private function pastikanMilik(Project $project, Resource $resource): void
    {
        abort_unless($resource->project_id === $project->id, 404);
    }
}
