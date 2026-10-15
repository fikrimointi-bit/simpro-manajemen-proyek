<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception khusus mesin penjadwalan (App\Services\SchedulerService).
 *
 * Semua pesan berbahasa Indonesia dan selalu dilempar SEBELUM ada tanggal
 * yang ditulis ke database, sehingga perubahan yang menyebabkan kegagalan
 * tidak pernah tersimpan.
 */
class SchedulingException extends RuntimeException
{
    /** Dependensi membentuk siklus (mis. A → B → A). */
    public static function siklus(): self
    {
        return new self(
            'Siklus dependensi terdeteksi: urutan tugas saling kait '
            . '(A mendahului B dan B juga mendahului A), sehingga jadwal tidak dapat dihitung. '
            . 'Perubahan tidak disimpan.'
        );
    }

    /** Tipe link di luar FS/SS/FF/SF. */
    public static function tipeTidakDikenal(string $tipe): self
    {
        return new self(
            "Tipe dependensi '{$tipe}' tidak dikenal. Gunakan salah satu: FS, SS, FF, atau SF. "
            . 'Perubahan tidak disimpan.'
        );
    }
}
