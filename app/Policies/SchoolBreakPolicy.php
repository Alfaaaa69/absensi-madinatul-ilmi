<?php

namespace App\Policies;

use App\Models\SchoolBreak;
use App\Models\User;

class SchoolBreakPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SchoolBreak $schoolBreak): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SchoolBreak $schoolBreak): bool
    {
        return $user->isAdmin();
    }
}
