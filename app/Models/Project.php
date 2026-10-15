<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model proyek: induk dari tugas, sumber daya, dan hari libur. */
class Project extends Model
{
    /** Kolom yang boleh diisi lewat mass assignment. */
    protected $fillable = [
        'name',
        'organization',
        'website',
        'description',
        'start_date',
        'weekend_days',
        'weekend_mode',
    ];

    /** Cast kolom: tanggal jadi Carbon, weekend_days jadi array. */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'weekend_days' => 'array',
        ];
    }

    /** Hari libur proyek ini (sekali maupun berulang). */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /** Daftar tugas dalam proyek. */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Sumber daya (anggota tim) proyek. */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /** Ketergantungan antar tugas proyek ini (FS/SS/FF/SF). */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }
}
