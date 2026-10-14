<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hari libur proyek: bisa sekali pakai atau berulang tiap tahun. */
class Holiday extends Model
{
    protected $fillable = [
        'project_id',
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

    /** Proyek pemilik hari libur ini. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope: libur yang jatuh pada tanggal tertentu.
     * Libur sekali dibandingkan tanggal persis; libur berulang dibandingkan
     * bulan + tanggal saja (tahunnya diabaikan).
     */
    public function scopePadaTanggal(Builder $query, CarbonInterface $tanggal): Builder
    {
        return $query->where(function (Builder $q) use ($tanggal) {
            $q->whereDate('date', $tanggal->toDateString())
                ->orWhere(function (Builder $r) use ($tanggal) {
                    $r->where('is_recurring', true)
                        ->whereMonth('date', $tanggal->month)
                        ->whereDay('date', $tanggal->day);
                });
        });
    }
}
