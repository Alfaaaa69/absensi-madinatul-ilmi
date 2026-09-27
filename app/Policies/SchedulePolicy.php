<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;

class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin() || $schedule->teacher_id === $user->id;
    }

    /**
     * Gate untuk membuat baris absensi baru. Baris yang sudah ada
     * diotorisasi lewat AttendancePolicy::update.
     */
    public function recordAttendance(User $user, Schedule $schedule, Carbon $date): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($schedule->teacher_id !== $user->id) {
            return false;
        }

        $today = Carbon::today();

        return $date->greaterThanOrEqualTo($today->copy()->subDay())
            && $date->lessThanOrEqualTo($today);
    }
}
