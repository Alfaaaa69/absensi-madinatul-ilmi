<?php

namespace App\Attendance\Queries;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal milik satu pengguna untuk satu tanggal.
 *
 * Kandidat dipersempit dengan `teacher_id` supaya query tidak menarik
 * jadwal seluruh sekolah, lalu setiap kandidat tetap ditanyakan ke
 * SchedulePolicy sebagai satu-satunya sumber kebenaran. Tidak ada
 * kondisi role di kelas ini maupun di view.
 */
final class TodaySchedulesQuery
{
    /**
     * @return Collection<int,Schedule>
     */
    public function for(User $user, Carbon $tanggal): Collection
    {
        $hari = DayOfWeek::tryFrom((int) $tanggal->startOfDay()->dayOfWeekIso);

        if ($hari === null) {
            return new Collection;
        }

        $policy = app(SchedulePolicy::class);

        return Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day_of_week', $hari)
            ->orderBy('start_time')
            ->get()
            ->reject(fn (Schedule $schedule) => ! $policy->view($user, $schedule))
            ->values();
    }
}
