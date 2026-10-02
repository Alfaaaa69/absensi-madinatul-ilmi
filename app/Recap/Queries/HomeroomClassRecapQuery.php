<?php

namespace App\Recap\Queries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Recap\Queries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Support\Collection;
use LogicException;

final class HomeroomClassRecapQuery
{
    use BuildsRecapMatrix;

    private ?User $actor = null;

    private ?AcademicYear $academicYear = null;

    private ?SchoolClass $schoolClass = null;

    public function for(User $actor, AcademicYear $academicYear): self
    {
        $this->actor = $actor;
        $this->academicYear = $academicYear;

        return $this;
    }

    public function forClass(SchoolClass $schoolClass): self
    {
        $this->schoolClass = $schoolClass;

        return $this;
    }

    /**
     * Daftar kelas yang diwalikan, untuk pemilih kelas dan deteksi empty state.
     *
     * @return Collection<int,SchoolClass>
     */
    public function ownedClasses(User $actor, AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->where('homeroom_teacher_id', $actor->id)
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('name')
            ->get();
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->actor === null || $this->academicYear === null) {
            throw new LogicException('Panggil for() dengan user dan tahun ajaran sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        if ($this->schoolClass !== null
            && ($this->schoolClass->homeroom_teacher_id !== $this->actor->id
                || $this->schoolClass->academic_year_id !== $this->academicYear->id)) {
            throw new LogicException('Kelas yang dipilih bukan kelas yang diwalikan pada tahun ajaran ini.');
        }

        $kelas = SchoolClass::query()
            ->where('homeroom_teacher_id', $this->actor->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->when($this->schoolClass, fn ($query) => $query->whereKey($this->schoolClass->id))
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
