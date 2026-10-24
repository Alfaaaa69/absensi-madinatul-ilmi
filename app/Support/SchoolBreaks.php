<?php

namespace App\Support;

use App\Enums\DayOfWeek;
use App\Models\SchoolBreak;

final class SchoolBreaks
{
    /**
     * @return list<array{after: int, label: string, start: string, end: string}>
     */
    public static function forDay(DayOfWeek $day): array
    {
        return SchoolBreak::query()
            ->where('day_of_week', $day->value)
            ->orderBy('after')
            ->orderBy('start_time')
            ->get()
            ->map(fn (SchoolBreak $break) => [
                'after' => $break->after,
                'label' => $break->label,
                'start' => $break->start_time->format('H:i'),
                'end' => $break->end_time->format('H:i'),
            ])
            ->all();
    }

    public static function periodFor(DayOfWeek $day, string $startTime): ?int
    {
        if ($day !== DayOfWeek::Senin) {
            return null;
        }

        $jam = substr($startTime, 0, 5);
        $nomor = null;

        // Jam mulai di luar tabel (mis. 13:35) ikut jam terdekat sebelumnya,
        // supaya tidak jatuh ke nomor urut yang sudah dipakai jam lain.
        foreach ([
            '07:45' => 1,
            '08:20' => 2,
            '08:55' => 3,
            '09:30' => 4,
            '10:35' => 5,
            '11:10' => 6,
            '11:45' => 7,
            '12:50' => 8,
            '13:25' => 9,
        ] as $mulai => $slot) {
            if ($mulai <= $jam) {
                $nomor = $slot;
            }
        }

        return $nomor;
    }

    /**
     * @return array{after: int, label: string, start: string, end: string}|null
     */
    public static function overlap(DayOfWeek $day, string $startTime, string $endTime): ?array
    {
        $mulai = self::toSeconds($startTime);
        $selesai = self::toSeconds($endTime);

        foreach (self::forDay($day) as $jeda) {
            if ($mulai < self::toSeconds($jeda['end']) && self::toSeconds($jeda['start']) < $selesai) {
                return $jeda;
            }
        }

        return null;
    }

    private static function toSeconds(string $time): int
    {
        [$jam, $menit, $detik] = array_pad(explode(':', $time), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
}
