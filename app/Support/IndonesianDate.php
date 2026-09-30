<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class IndonesianDate
{
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function tanggal(Carbon $date): string
    {
        return sprintf(
            '%s, %d %s %d',
            self::HARI[(int) $date->dayOfWeekIso],
            (int) $date->day,
            self::BULAN[(int) $date->month],
            (int) $date->year,
        );
    }

    public static function jam(Carbon $waktu): string
    {
        return $waktu->format('H:i');
    }
}
