<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Bantuan format tanggal berbahasa Indonesia (tanpa dependensi locale). */
class Tanggal
{
    /** Nama hari, diurutkan sesuai dayOfWeek Carbon (0 = Minggu). */
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** Nama bulan, diurutkan sesuai bulan 1-12. */
    private const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /** Format panjang: "Senin, 14 Oktober 2026". */
    public static function panjang(CarbonInterface|string $date): string
    {
        $d = Carbon::parse($date);

        return sprintf(
            '%s, %d %s %d',
            self::HARI[$d->dayOfWeek],
            $d->day,
            self::BULAN[$d->month - 1],
            $d->year
        );
    }

    /** Format pendek: "14 Okt 2026". */
    public static function pendek(CarbonInterface|string $date): string
    {
        $d = Carbon::parse($date);
        $bulanPendek = array_map(
            fn (string $b) => mb_substr($b, 0, 3),
            self::BULAN
        );

        return sprintf('%d %s %d', $d->day, $bulanPendek[$d->month - 1], $d->year);
    }

    /** Nama hari dari angka dayOfWeek Carbon, contoh: 0 -> "Minggu". */
    public static function namaHari(int $dayOfWeek): string
    {
        return self::HARI[$dayOfWeek] ?? '-';
    }
}
