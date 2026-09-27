<?php

namespace App\Recap\Queries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Recap\Queries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use LogicException;

final class TeacherSubjectRecapQuery
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

        $jadwal = Schedule::query()
            ->where('teacher_id', $this->actor->id)
            ->whereIn('class_id', SchoolClass::query()
                ->where('academic_year_id', $this->academicYear->id)
                ->pluck('id'))
            ->with('subject')
            ->get();

        $kelas = SchoolClass::query()
            ->whereIn('id', $jadwal->pluck('class_id')->unique())
            ->orderBy('name')
            ->get();

        $siswa = Student::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('schoolClass')
            ->get();

        return $this->buildResult($kelas, $jadwal, $period, $siswa);
    }
}
