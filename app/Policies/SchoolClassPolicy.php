<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin();
    }

    /**
     * Tahun ajaran ikut diperiksa, bukan hanya nama wali.
     *
     * Kelas dibuat ulang tiap tahun, jadi wali yang sama punya baris `classes` yang
     * berbeda untuk ganjil dan genap. Kalau ability ini hanya membandingkan
     * `homeroom_teacher_id`, kelas milik tahun lalu tetap lolos - dan karena
     * `HomeroomClassRecapQuery::forClass()` hanya menerima kelas dari tahun yang
     * dipilih, hasilnya bukan 403 melainkan exception 500 yang tidak menjelaskan
     * apa pun ke pengguna.
     */
    public function viewRecap(User $user, SchoolClass $schoolClass, ?AcademicYear $academicYear = null): bool
    {
        if (! $user->isAdmin() && $schoolClass->homeroom_teacher_id !== $user->id) {
            return false;
        }

        return $academicYear === null || $schoolClass->academic_year_id === $academicYear->id;
    }
}
