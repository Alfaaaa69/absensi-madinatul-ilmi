<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    /**
     * @var array<string, string>
     */
    private const WARNA_STATUS = [
        'hadir' => '#059669',
        'izin' => '#2563eb',
        'sakit' => '#d97706',
        'alpa' => '#dc2626',
    ];

    /**
     * @var array<string, string>
     */
    private const LABEL_STATUS = [
        'hadir' => 'Hadir',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'alpa' => 'Alfa',
    ];

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

        $isAdmin = $user->isAdmin();

        $chart7Hari = $isAdmin && $academicYear !== null ? $this->last7DaysChart($academicYear) : null;
        $chartHariIni = $isAdmin && $academicYear !== null ? $this->todayChart($academicYear) : null;

        return view('livewire.dashboard', [
            'academicYear' => $academicYear,
            'schedules' => $schedules,
            'counts' => $isAdmin
                ? $this->adminTodayCounts($academicYear)
                : $this->todayCounts($schedules->pluck('id')),
            'homeroomCount' => $isAdmin
                ? SchoolClass::query()->count()
                : $user->homeroomClasses()->count(),
            'chartLast7Days' => $chart7Hari['config'] ?? null,
            'last7HasData' => $chart7Hari['hasData'] ?? false,
            'chartToday' => $chartHariIni['config'] ?? null,
            'todayHasData' => $chartHariIni['hasData'] ?? false,
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
     * Admin tidak punya jadwal mengajar sendiri, jadi ringkasan hariannya
     * dihitung dari seluruh absensi hari ini pada jadwal tahun ajaran aktif.
     *
     * @return array{hadir: int, izin: int, sakit: int, alpa: int}
     */
    private function adminTodayCounts(?AcademicYear $year): array
    {
        $counts = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($year === null) {
            return $counts;
        }

        $tanggal = today();
        $hariIni = $this->statusCountsBetween($year, $tanggal, $tanggal);

        foreach (array_keys($counts) as $status) {
            $counts[$status] = $hariIni[$tanggal->toDateString().'|'.$status] ?? 0;
        }

        return $counts;
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
     * @return array{config: array<string, mixed>, hasData: bool}
     */
    private function last7DaysChart(AcademicYear $year): array
    {
        $selesai = today();

        $counts = $this->statusCountsBetween($year, $selesai->copy()->subDays(6), $selesai);

        $labels = [];
        $perStatus = [
            'hadir' => [],
            'izin' => [],
            'sakit' => [],
            'alpa' => [],
        ];

        for ($i = 6; $i >= 0; $i--) {
            $tanggal = $selesai->copy()->subDays($i);
            $labels[] = $tanggal->format('d/m');

            foreach ($perStatus as $status => &$nilai) {
                $nilai[] = $counts[$tanggal->toDateString().'|'.$status] ?? 0;
            }
            unset($nilai);
        }

        $datasets = [];

        foreach ($perStatus as $status => $nilai) {
            $datasets[] = [
                'label' => self::LABEL_STATUS[$status],
                'data' => $nilai,
                'borderColor' => self::WARNA_STATUS[$status],
                'backgroundColor' => self::WARNA_STATUS[$status],
                'tension' => 0.3,
                'spanGaps' => true,
            ];
        }

        return [
            'config' => [
                'type' => 'line',
                'data' => ['labels' => $labels, 'datasets' => $datasets],
                'options' => $this->chartOptions(),
            ],
            'hasData' => collect($perStatus)->flatten()->sum() > 0,
        ];
    }

    /**
     * @return array{config: array<string, mixed>, hasData: bool}
     */
    private function todayChart(AcademicYear $year): array
    {
        $counts = $this->statusCountsBetween($year, today(), today());
        $kunci = today()->toDateString();
        $data = [];

        foreach (self::LABEL_STATUS as $status => $label) {
            $data[] = $counts[$kunci.'|'.$status] ?? 0;
        }

        return [
            'config' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => array_values(self::LABEL_STATUS),
                    'datasets' => [
                        ['data' => $data, 'backgroundColor' => array_values(self::WARNA_STATUS)],
                    ],
                ],
                'options' => $this->chartOptions(),
            ],
            'hasData' => array_sum($data) > 0,
        ];
    }

    /**
     * Jumlah absensi per status dalam rentang tanggal, dibatasi ke jadwal kelas
     * pada tahun ajaran itu. Jadwal/kelas yang diarsipkan ikut diabaikan, sama
     * seperti query rekap admin.
     *
     * @return array<string, int>
     */
    private function statusCountsBetween(AcademicYear $year, Carbon $mulai, Carbon $selesai): array
    {
        $rows = DB::table('attendances')
            ->selectRaw('date, status, COUNT(*) as total')
            ->whereBetween('date', [$mulai->copy()->startOfDay(), $selesai->copy()->endOfDay()])
            ->whereIn('schedule_id', Schedule::query()
                ->whereIn('class_id', SchoolClass::query()
                    ->where('academic_year_id', $year->id)
                    ->select('id'))
                ->select('id'))
            ->groupBy('date', 'status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[Carbon::parse($row->date)->toDateString().'|'.$row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
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
