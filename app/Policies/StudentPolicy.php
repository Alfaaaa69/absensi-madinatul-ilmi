<?php

namespace App\Policies;

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

        return $student->schoolClass->schedules()
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
