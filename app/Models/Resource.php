<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sumber daya: anggota tim yang dialokasikan ke tugas-tugas. */
class Resource extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'email',
        'role',
        'capacity',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'float',
        ];
    }

    /** Proyek pemilik sumber daya. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Tugas yang dikerjakan sumber daya ini (pivot resource_task). */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'resource_task')
            ->withPivot('effort')
            ->withTimestamps();
    }

    /** Hari libur khusus sumber daya (cuti, sakit, dsb.). */
    public function daysOff(): HasMany
    {
        return $this->hasMany(ResourceDayOff::class);
    }
}
