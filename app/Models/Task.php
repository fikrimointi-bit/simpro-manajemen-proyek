<?php

namespace App\Models;

use App\Services\CalendarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tugas/tahapan dalam proyek, bisa bersub-tugas (struktur WBS). */
class Task extends Model
{
    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'start_date',
        'end_date',
        'duration',
        'priority',
        'progress',
        'color',
        'is_milestone',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'is_milestone' => 'bool',
            'position' => 'integer',
        ];
    }

    /** Proyek pemilik tugas. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tugas induk (null untuk tugas level teratas). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /** Sub-tugas dari tugas ini. */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /** Dependensi keluar: tugas ini mendahului tugas lain. */
    public function successors(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    /** Dependensi masuk: tugas ini mengikuti tugas lain. */
    public function predecessors(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_id');
    }

    /** Sumber daya yang dialokasikan ke tugas ini (pivot resource_task). */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_task')
            ->withPivot('effort')
            ->withTimestamps();
    }

    /** Apakah tugas ini punya sub-tugas (jadwal dihitung dari anak)? */
    public function isInduk(): bool
    {
        return $this->subtasks()->exists();
    }

    /**
     * Kumpulkan ID seluruh turunan (anak, cucu, dst.) secara rekursif.
     * Dipakai untuk cegah loop induk dan untuk hapus beserta turunannya.
     */
    public function descendantIds(): array
    {
        // susun pohon anak dari seluruh tugas proyek (sekali query)
        $anakPerInduk = $this->project->tasks()->get()
            ->groupBy(fn (Task $t) => $t->parent_id ?? 'root');

        $hasil = [];
        $antrean = [$this->id];

        while ($antrean) {
            $id = array_shift($antrean);

            foreach ($anakPerInduk->get($id, collect()) as $t) {
                $hasil[] = $t->id;
                $antrean[] = $t->id;
            }
        }

        return $hasil;
    }

    /**
     * Hitung ulang jadwal tugas ini dari sub-tugasnya, lalu teruskan ke induk.
     *
     * Tugas induk: mulai = mulai paling awal anak, selesai = selesai paling
     * akhir anak, durasi = jumlah hari kerja inklusif di rentang tersebut.
     * Tugas tanpa anak dibiarkan (tanggal & durasinya sudah dihitung controller
     * memakai CalendarService). Memakai updateQuietly agar tidak memicu event.
     */
    public function recomputeSchedule(CalendarService $kalender): void
    {
        $anak = $this->subtasks()->get();

        if ($anak->isNotEmpty()) {
            $mulai = $anak->whereNotNull('start_date')->min('start_date');
            $akhir = $anak->whereNotNull('end_date')->max('end_date');

            $this->updateQuietly([
                'start_date' => $mulai,
                'end_date' => $akhir,
                'duration' => ($mulai && $akhir)
                    ? $kalender->countWorkingDays($this->project, $mulai, $akhir)
                    : null,
            ]);
        }

        // induk di atas kita juga perlu menyesuaikan agregatnya
        $this->parent?->recomputeSchedule($kalender);
    }
}
