<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Model pivot alokasi sumber daya ke tugas (tabel resource_task). */
class ResourceTask extends Model
{
    /** Nama tabel eksplisit karena Laravel akan menyari-kan jadi resource_tasks. */
    protected $table = 'resource_task';

    protected $fillable = [
        'resource_id',
        'task_id',
        'effort',
    ];

    protected function casts(): array
    {
        return [
            'effort' => 'float',
        ];
    }

    /** Sumber daya yang dialokasikan. */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** Tugas yang menerima alokasi. */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
