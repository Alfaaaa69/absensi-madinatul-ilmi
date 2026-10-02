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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /**
     * Riwayat harian seorang siswa untuk satu periode, hanya dari kelas yang
     * diwalikan `$actor` pada `$academicYear`. Siswa dari kelas lain
     * menghasilkan collection kosong.
     *
     * `DB::table` dipakai supaya `status` tetap string biasa; lewat model
     * `Attendance` nilainya jadi enum dan `match` di component harus memakai
     * enum. Query ini tetap satu-satunya tempat yang tahu siswa mana milik
     * `$actor` — component tidak melakukan query apa pun.
     *
     * @return Collection<int,array{date: string, mapel: string, status: string, note: string|null}>
     */
    public function studentHistory(
        User $actor,
        AcademicYear $academicYear,
        Student $student,
        RecapPeriod $period,
    ): Collection {
        $kelasIds = SchoolClass::query()
            ->where('homeroom_teacher_id', $actor->id)
            ->where('academic_year_id', $academicYear->id)
            ->whereKey($student->class_id)
            ->pluck('id');

        $jadwalIds = $kelasIds->isEmpty()
            ? collect()
            : Schedule::query()->whereIn('class_id', $kelasIds)->pluck('id');

        if ($jadwalIds->isEmpty()) {
            return new Collection;
        }

        $period->assertWithin($academicYear);

        $akhir = $period->countedEnd();

        if ($akhir === null) {
            return new Collection;
        }

        return DB::table('attendances')
            ->join('schedules', 'schedules.id', '=', 'attendances.schedule_id')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->where('attendances.student_id', $student->id)
            ->whereIn('attendances.schedule_id', $jadwalIds)
            ->whereBetween('attendances.date', [$period->start->toDateString(), $akhir->toDateString()])
            ->orderBy('attendances.date')
            ->orderBy('subjects.name')
            ->get(['attendances.date', 'subjects.name as mapel', 'attendances.status', 'attendances.note'])
            ->map(fn (object $baris): array => [
                'date' => (string) $baris->date,
                'mapel' => (string) $baris->mapel,
                'status' => (string) $baris->status,
                'note' => $baris->note !== null ? (string) $baris->note : null,
            ]);
    }
}
