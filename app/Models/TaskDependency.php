<?php

namespace App\Models;

use App\Services\SchedulerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ketergantungan antar tugas (mis. finish-to-start ala GanttProject).
 *
 * Peta kolom (nama lama dipertahankan — JANGAN rename):
 * - `predecessor_id` → tugas yang mendahului (pendahulu).
 * - `successor_id`   → tugas yang MENUNGGU dijadwalkan mengikuti pendahulu
 *                      (setara "task_id" pada skema rencana).
 * - `type`           → FS, SS, FF, atau SF.
 * - `lag_days`       → delay / jeda hari kerja antar tugas (boleh negatif).
 */
class TaskDependency extends Model
{
    protected $fillable = [
        'project_id',
        'predecessor_id',
        'successor_id',
        'type',
        'lag_days',
    ];

    protected function casts(): array
    {
        return [
            'lag_days' => 'integer', // delay dalam hari
        ];
    }

    /**
     * Kaitkan setiap penyimpanan/penghapusan dependensi dengan mesin penjadwalan.
     *
     * - `saving` : TOLAK siklus (A → B → A) sebelum baris masuk database,
     *              jadi siklus tidak pernah tersimpan ("perubahan tidak disimpan").
     * - `saved` / `deleted` : jadwalkan ulang seluruh proyek karena dependensi berubah.
     */
    protected static function booted(): void
    {
        static::saving(function (TaskDependency $dependensi): void {
            app(SchedulerService::class)->assertNoCycle($dependensi);
        });

        static::saved(function (TaskDependency $dependensi): void {
            if ($dependensi->project) {
                app(SchedulerService::class)->scheduleProject($dependensi->project);
            }
        });

        static::deleted(function (TaskDependency $dependensi): void {
            if ($dependensi->project) {
                app(SchedulerService::class)->scheduleProject($dependensi->project);
            }
        });
    }

    /** Proyek tempat relasi ini berada. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tugas yang mendahului (pendahulu). */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    /** Tugas yang menunggu / mengikuti pendahulu (successor). */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }
}
