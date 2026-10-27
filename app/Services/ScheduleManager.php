<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Enums\HasilHapus;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\ScheduleConflictDetector;
use App\Support\SchoolBreaks;
use Illuminate\Validation\ValidationException;

final class ScheduleManager
{
    private const MAKS_KELAS_PER_GURU = 3;

    public function __construct(
        private readonly ScheduleConflictDetector $detector,
        private readonly MasterDeletion $deletion,
    ) {}

    /**
     * @param  array{class_id: string, subject_id: string, teacher_id: string, day_of_week: string, start_time: string, end_time: string}  $data
     */
    public function save(?Schedule $schedule, array $data): Schedule
    {
        $guru = User::findOrFail($data['teacher_id']);
        $hari = DayOfWeek::from((int) $data['day_of_week']);

        $this->validasiJeda($hari, $data['start_time'], $data['end_time']);

        $this->validasiBatasKelas($guru, $data['class_id'], $schedule?->id);

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

    public function hapus(Schedule $schedule): HasilHapus
    {
        return $this->deletion->hapus($schedule);
    }

    public function pulihkan(Schedule $schedule): void
    {
        $this->validasiJeda(
            $schedule->day_of_week,
            $schedule->start_time->format('H:i'),
            $schedule->end_time->format('H:i'),
        );

        $schedule->restore();
    }

    private function validasiJeda(DayOfWeek $hari, string $startTime, string $endTime): void
    {
        $jeda = SchoolBreaks::overlap($hari, $startTime, $endTime);

        if ($jeda !== null) {
            throw ValidationException::withMessages([
                'start_time' => "Jam {$jeda['start']}–{$jeda['end']} dipakai untuk {$jeda['label']}.",
            ]);
        }
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

    private function validasiBatasKelas(User $guru, string $classId, ?string $abaikanScheduleId): void
    {
        $kelas = SchoolClass::withTrashed()->findOrFail($classId);

        /** @var list<string> $kelasUnik */
        $kelasUnik = Schedule::query()
            ->where('teacher_id', $guru->id)
            ->whereIn('class_id', SchoolClass::query()
                ->where('academic_year_id', $kelas->academic_year_id)
                ->select('id'))
            ->when($abaikanScheduleId !== null, fn ($q) => $q->where('id', '!=', $abaikanScheduleId))
            ->distinct()
            ->pluck('class_id')
            ->all();

        if (! in_array($classId, $kelasUnik, true) && count($kelasUnik) >= self::MAKS_KELAS_PER_GURU) {
            throw ValidationException::withMessages([
                'class_id' => "Guru {$guru->name} sudah mengajar ".self::MAKS_KELAS_PER_GURU.' kelas berbeda pada tahun ajaran ini (maksimal '.self::MAKS_KELAS_PER_GURU.' kelas).',
            ]);
        }
    }
}
