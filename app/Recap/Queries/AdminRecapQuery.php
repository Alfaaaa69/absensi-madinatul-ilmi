<?php

namespace App\Recap\Queries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\Queries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use App\Recap\RecapResult;
use LogicException;

final class AdminRecapQuery
{
    use BuildsRecapMatrix;

    private ?AcademicYear $academicYear = null;

    private ?SchoolClass $schoolClass = null;

    public function forAcademicYear(AcademicYear $academicYear): self
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    public function forClass(SchoolClass $schoolClass): self
    {
        $this->schoolClass = $schoolClass;

        return $this;
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->academicYear === null) {
            throw new LogicException('Panggil forAcademicYear() sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        if ($this->schoolClass !== null && $this->schoolClass->academic_year_id !== $this->academicYear->id) {
            throw RecapPeriodOutOfRange::forClassOutsideYear($this->schoolClass, $this->academicYear);
        }

        $kelas = SchoolClass::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->when($this->schoolClass, fn ($query) => $query->whereKey($this->schoolClass->id))
            ->with('homeroomTeacher')
            ->orderBy('name')
            ->get();

        $jadwal = Schedule::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('subject')
            ->get();

        $siswa = Student::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('schoolClass')
            ->get();

        return $this->buildResult($kelas, $jadwal, $period, $siswa);
    }
}
