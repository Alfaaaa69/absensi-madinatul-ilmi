<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\User;
use DateTimeInterface;

final class ScheduleConflictDetector
{
    /**
     * @return array<int,Schedule> jadwal yang waktunya bertindih
     */
    public function findConflicts(
        User $teacher,
        int $dayOfWeek,
        string|DateTimeInterface $startTime,
        string|DateTimeInterface $endTime,
        ?string $ignoreScheduleId = null,
    ): array {
        $mulai = $this->toSeconds($startTime);
        $selesai = $this->toSeconds($endTime);

        return Schedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->get()
            ->filter(function (Schedule $schedule) use ($mulai, $selesai) {
                $jadwalMulai = $this->toSeconds($schedule->start_time);
                $jadwalSelesai = $this->toSeconds($schedule->end_time);

                return $mulai < $jadwalSelesai && $jadwalMulai < $selesai;
            })
            ->values()
            ->all();
    }

    private function toSeconds(string|DateTimeInterface $waktu): int
    {
        $jam = $waktu instanceof DateTimeInterface
            ? $waktu->format('H:i:s')
            : (string) $waktu;

        [$jam, $menit, $detik] = array_pad(explode(':', $jam), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
}
