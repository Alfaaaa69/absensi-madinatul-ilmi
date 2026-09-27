<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = $this->user();

        $academicYear = AcademicYear::query()
            ->where('is_active', true)
            ->first();

        $schedules = Schedule::query()
            ->where('teacher_id', $user->id)
            ->where('day_of_week', now()->dayOfWeekIso)
            ->with(['subject', 'schoolClass'])
            ->orderBy('start_time')
            ->get();

        return view('livewire.dashboard', [
            'academicYear' => $academicYear,
            'schedules' => $schedules,
            'counts' => $this->todayCounts($schedules->pluck('id')),
            'homeroomCount' => $user->isAdmin()
                ? SchoolClass::query()->count()
                : $user->homeroomClasses()->count(),
        ]);
    }

    #[Computed]
    public function getSalamProperty(): string
    {
        $jam = now()->hour;

        return match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /**
     * @param  Collection<int, string>  $scheduleIds
     * @return array{hadir: int, izin: int, sakit: int, alpa: int}
     */
    private function todayCounts(Collection $scheduleIds): array
    {
        $statuses = [
            AttendanceStatus::Hadir->value => 'hadir',
            AttendanceStatus::Izin->value => 'izin',
            AttendanceStatus::Sakit->value => 'sakit',
            AttendanceStatus::Alpa->value => 'alpa',
        ];

        $counts = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($scheduleIds->isEmpty()) {
            return $counts;
        }

        $rows = Attendance::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('date', today())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($rows as $status => $total) {
            if (isset($statuses[$status])) {
                $counts[$statuses[$status]] = (int) $total;
            }
        }

        return $counts;
    }

    /**
     * Route /dashboard ada di dalam grup auth + EnsureUserIsActive, jadi user
     * di sini selalu ada. Null dicek eksplisit supaya komponen gagal dengan
     * pesan yang jelas, bukan fatal error, kalau proteksinya suatu saat berubah.
     */
    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
