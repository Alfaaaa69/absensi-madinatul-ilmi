<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->taughtSchedules()->exists();
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Dicocokkan lewat class_id, bukan $student->schoolClass->schedules(): pemanggil
        // boleh mengirim siswa yang diambil dari daftar banyak baris, dan model seperti
        // itu akan menolak lazy loading.
        return Schedule::query()
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }
}
