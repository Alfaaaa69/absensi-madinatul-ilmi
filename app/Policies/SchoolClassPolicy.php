<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewRecap(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin() || $schoolClass->homeroom_teacher_id === $user->id;
    }
}
