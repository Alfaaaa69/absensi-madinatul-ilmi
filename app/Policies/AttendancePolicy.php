<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return app(SchedulePolicy::class)->recordAttendance(
            $user,
            $attendance->schedule,
            $attendance->date,
        );
    }
}
