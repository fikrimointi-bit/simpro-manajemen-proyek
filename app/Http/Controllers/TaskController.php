<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\CalendarService;
use App\Services\SchedulerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CRUD tugas bersarang di dalam proyek (projects/{project}/tasks). */
class TaskController extends Controller
{
    public function __construct(
        private CalendarService $kalender,
        private SchedulerService $scheduler
    ) {
    }

    /** Daftar tugas proyek, ditampilkan berjenjang sesuai induk-anak. */
    public function index(Project $project): View
    {
        $tugas = $project->tasks()->orderBy('id')->get();

        return view('tasks.index', [
            'project' => $project,
            'baris' => $this->susunPohon($tugas),
            'jumlahAnak' => $tugas->countBy('parent_id'),
        ]);
    }

    /** Form tugas baru; ?parent_id= untuk membuat sub-tugas. */
    public function create(Project $project, Request $request): View
    {
        $parent = null;

        if ($parentId = $request->integer('parent_id')) {
            $parent = $project->tasks()->whereKey($parentId)->firstOrFail();
        }

        return view('tasks.create', [
            'project' => $project,
            'parent' => $parent,
            'tugasLain' => $project->tasks()->orderBy('id')->get(),
        ]);
    }

    /** Simpan tugas baru (induk maupun anak). */
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $parent = ! empty($data['parent_id'])
            ? $project->tasks()->findOrFail($data['parent_id'])
            : null;

        // jadwal diisi lengkap = tugas berjadwal (anak maupun mandiri);
        // tanpa jadwal = tugas induk, tanggal & durasi menyusul dari sub-tugas
        $berjadwal = ! empty($data['start_date']) && ! empty($data['duration']);

        if ($parent || $berjadwal) {
            // akhir = hari kerja terakhir (durasi - 1) dari tanggal mulai
            $data['end_date'] = $this->kalender
                ->addWorkingDays($project, $data['start_date'], max((int) $data['duration'] - 1, 0))
                ->toDateString();
        } else {
            // tugas induk: tanggal & durasi menyusul dari sub-tugas
            $data['start_date'] = null;
            $data['end_date'] = null;
            $data['duration'] = null;
        }

        // urutan tampil: urutkan per induk, bukan global
        $data['position'] = $project->tasks()
            ->where('parent_id', $data['parent_id'] ?? null)
            ->count();

        $task = $project->tasks()->create($data);

        // perbarui agregat induk (kalau ada) sampai ke puncak pohon
        ($parent ?? $task)->recomputeSchedule($this->kalender);

        // tugas berubah -> jadwalkan ulang seluruh proyek
        $this->scheduler->scheduleProject($project);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', $parent
                ? 'Sub-tugas berhasil ditambahkan.'
                : 'Tugas berhasil ditambahkan.');
    }

    /** Form edit tugas. */
    public function edit(Project $project, Task $task): View
    {
        $this->pastikanMilik($project, $task);

        // kandidat induk: bukan dirinya sendiri dan bukan turunannya (cegah loop)
        $dilarang = array_merge([$task->id], $task->descendantIds());

        return view('tasks.edit', [
            'project' => $project,
            'task' => $task,
            'tugasLain' => $project->tasks()->whereKeyNotIn($dilarang)->orderBy('id')->get(),
        ]);
    }

    /** Simpan perubahan tugas. */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->pastikanMilik($project, $task);

        $data = $request->validated();
        $indukLamaId = $task->parent_id;

        if ($task->isInduk()) {
            // tugas induk: tanggal & durasi selalu hasil hitungan anak
            unset($data['start_date'], $data['duration']);
            $data['end_date'] = $task->end_date;
        } elseif (! empty($data['start_date']) && ! empty($data['duration'])) {
            // tugas berjadwal: akhir dihitung ulang dari hari kerja proyek
            $data['end_date'] = $this->kalender
                ->addWorkingDays($project, $data['start_date'], max((int) $data['duration'] - 1, 0))
                ->toDateString();
        } else {
            // jadwal dikosongkan (tugas induk yang belum punya sub-tugas)
            $data['start_date'] = null;
            $data['end_date'] = null;
            $data['duration'] = null;
        }

        $task->update($data);

        // hitung ulang agregat: induk baru, induk lama (jika pindah), dan di atasnya
        $task->recomputeSchedule($this->kalender);

        if ($indukLamaId && $indukLamaId !== $task->parent_id) {
            $indukLama = $project->tasks()->find($indukLamaId);
            $indukLama?->recomputeSchedule($this->kalender);
        }

        // tugas berubah -> jadwalkan ulang seluruh proyek
        $this->scheduler->scheduleProject($project);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /** Hapus tugas; sub-tugas ikut terhapus (diminta pada deskripsi tugas). */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->pastikanMilik($project, $task);

        $turunan = $task->descendantIds();
        $indukId = $task->parent_id;

        // hapus seluruh subtree sekaligus (tetap aman walau FK cascade belum aktif)
        Task::whereIn('id', array_merge([$task->id], $turunan))->delete();

        // induk yang tersisa perlu menghitung ulang agregatnya
        $indukId
            ? $project->tasks()->find($indukId)?->recomputeSchedule($this->kalender)
            : null;

        // tugas berubah -> jadwalkan ulang seluruh proyek
        $this->scheduler->scheduleProject($project);

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('success', $turunan
                ? 'Tugas beserta '.count($turunan).' sub-tugas berhasil dihapus.'
                : 'Tugas berhasil dihapus.');
    }

    /** Tolak akses tugas yang bukan milik proyek di URL. */
    private function pastikanMilik(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }

    /**
     * Susun daftar tugas berjenjang (DFS): setiap baris membawa tugas +
     * tingkat kedalaman untuk indentasi tampilan.
     */
    private function susunPohon($tugas): array
    {
        $anakPerInduk = $tugas->groupBy(fn (Task $t) => $t->parent_id ?? 'root');

        $baris = [];
        $kunjung = function ($idList, int $depth) use (&$kunjung, $anakPerInduk, &$baris) {
            foreach ($anakPerInduk->get($idList, collect()) as $t) {
                $baris[] = ['task' => $t, 'depth' => $depth];
                $kunjung((string) $t->id, $depth + 1);
            }
        };

        $kunjung('root', 0);

        return $baris;
    }
}
