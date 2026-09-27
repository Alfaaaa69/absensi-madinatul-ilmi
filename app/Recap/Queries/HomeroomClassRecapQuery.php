<?php

namespace App\Recap\Queries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Recap\Queries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use LogicException;

final class HomeroomClassRecapQuery
{
    use BuildsRecapMatrix;

    private ?User $actor = null;

    private ?AcademicYear $academicYear = null;

    public function for(User $actor, AcademicYear $academicYear): self
    {
        $this->actor = $actor;
        $this->academicYear = $academicYear;

        return $this;
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->actor === null || $this->academicYear === null) {
            throw new LogicException('Panggil for() dengan user dan tahun ajaran sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        $kelas = SchoolClass::query()
            ->where('homeroom_teacher_id', $this->actor->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->with('students.schoolClass')
            ->orderBy('name')
            ->get();

        $jadwal = Schedule::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('subject')
            ->get();

        return $this->buildResult(
            $kelas,
            $jadwal,
            $period,
            $kelas->flatMap(fn (SchoolClass $k) => $k->students),
        );
    }
}
