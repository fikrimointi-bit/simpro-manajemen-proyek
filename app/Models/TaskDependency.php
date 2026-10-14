<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ketergantungan antar tugas (mis. finish-to-start ala GanttProject). */
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
            'lag_days' => 'integer',
        ];
    }

    /** Proyek tempat relasi ini berada. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tugas yang mendahului. */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    /** Tugas yang mengikuti. */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }
}
