<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hari libur khusus sumber daya (cuti, sakit, merayakan hari besar). */
class ResourceDayOff extends Model
{
    /** Nama tabel bawaan Laravel (resource_day_offs) tidak dipakai. */
    protected $table = 'resource_days_off';

    protected $fillable = [
        'project_id',
        'resource_id',
        'date',
        'description',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'bool',
        ];
    }

    /** Sumber daya pemilik hari libur ini. */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** Proyek tempat sumber daya ini terdaftar. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
