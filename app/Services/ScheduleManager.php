<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ScheduleConflictDetector;
use Illuminate\Validation\ValidationException;

final class ScheduleManager
{
    public function __construct(private readonly ScheduleConflictDetector $detector) {}

    /**
     * @param  array{class_id: string, subject_id: string, teacher_id: string, day_of_week: string, start_time: string, end_time: string}  $data
     */
    public function save(?Schedule $schedule, array $data): Schedule
    {
        $guru = User::findOrFail($data['teacher_id']);
        $hari = DayOfWeek::from((int) $data['day_of_week']);

        $bentrok = $this->detector->findConflicts(
            $guru,
            $hari->value,
            $data['start_time'],
            $data['end_time'],
            $schedule?->id,
        );

        if ($bentrok !== []) {
            throw ValidationException::withMessages([
                'start_time' => $this->pesanBentrok($bentrok),
            ]);
        }

        $jadwal = $schedule ?? new Schedule;

        $jadwal->fill([
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $guru->id,
            'day_of_week' => $hari,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);
        $jadwal->save();

        return $jadwal;
    }

    public function arsipkan(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function pulihkan(Schedule $schedule): void
    {
        $schedule->restore();
    }

    /**
     * @param  array<int,Schedule>  $bentrok
     */
    private function pesanBentrok(array $bentrok): string
    {
        $rincian = collect($bentrok)
            ->map(fn (Schedule $j) => sprintf(
                '%s - %s (%s %s-%s)',
                $j->schoolClass->name,
                $j->subject->name,
                $j->day_of_week->label(),
                $j->start_time->format('H:i'),
                $j->end_time->format('H:i'),
            ))
            ->implode('; ');

        return "Jam guru sudah dipakai: {$rincian}.";
    }
}
