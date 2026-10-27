<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
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
        'hadir' => '#276763',
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

        $hariIni = DayOfWeek::tryFrom((int) now()->dayOfWeekIso);

        $schedules = $hariIni === null
            ? collect()
            : Schedule::query()
                ->where('teacher_id', $user->id)
                ->where('day_of_week', $hariIni)
                ->with(['subject', 'schoolClass'])
                ->orderBy('start_time')
                ->get();

        $isAdmin = $user->isAdmin();

        $chart7Hari = $isAdmin && $academicYear !== null ? $this->last7DaysChart($academicYear) : null;
        $chartHariIni = $isAdmin && $academicYear !== null ? $this->todayChart($academicYear) : null;

        $homeroomCount = $isAdmin
            ? SchoolClass::query()->count()
            : $user->homeroomClasses()->count();

        $barisJadwal = null;
        $progres = null;
        $kelasDiampu = null;
        $kartuKelas = ['label' => 'Kelas Diawasi', 'nilai' => $homeroomCount];

        if (! $isAdmin) {
            $barisJadwal = $this->barisJadwalHariIni($schedules);

            $progres = [
                'sudah' => $barisJadwal->where('sudahAbsen', true)->count(),
                'total' => $barisJadwal->count(),
            ];

            $kelasDiampu = $academicYear === null ? 0 : $this->jumlahKelasDiampu($user, $academicYear);

            $kartuKelas = $homeroomCount > 0
                ? ['label' => 'Kelas Wali', 'nilai' => $homeroomCount]
                : ['label' => 'Kelas Diampu', 'nilai' => $kelasDiampu];
        }

        return view('livewire.dashboard', [
            'isAdmin' => $isAdmin,
            'academicYear' => $academicYear,
            'schedules' => $schedules,
            'counts' => $isAdmin
                ? $this->adminTodayCounts($academicYear)
                : $this->todayCounts($schedules->pluck('id')),
            'homeroomCount' => $homeroomCount,
            'kartuKelas' => $kartuKelas,
            'barisJadwal' => $barisJadwal,
            'progres' => $progres,
            'kelasDiampu' => $kelasDiampu,
            'chartLast7Days' => $chart7Hari['config'] ?? null,
            'chartToday' => $chartHariIni['config'] ?? null,
        ]);
    }

    /**
     * Jadwal guru hari ini dilengkapi penanda sudah/belum diabsen dan jumlah
     * siswa, supaya dashboard bisa langsung dipakai bekerja. Pola dan empty
     * state-nya sengaja sama dengan halaman /jadwal (TodaySchedule).
     *
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, array{jadwal: Schedule, jumlahSiswa: int, sudahAbsen: bool}>
     */
    private function barisJadwalHariIni(Collection $schedules): Collection
    {
        // withTrashed() sama seperti /jadwal: kelas yang diarsipkan tetap punya
        // siswa, jadi jumlahnya tidak salah tampil 0.
        $jumlahSiswa = SchoolClass::query()
            ->withTrashed()
            ->withCount('students')
            ->whereIn('id', $schedules->pluck('class_id')->unique())
            ->pluck('students_count', 'id');

        $tercatat = Attendance::query()
            ->whereIn('schedule_id', $schedules->pluck('id'))
            ->whereDate('date', today())
            ->selectRaw('schedule_id, count(*) as total')
            ->groupBy('schedule_id')
            ->pluck('total', 'schedule_id');

        return $schedules->map(fn (Schedule $item): array => [
            'jadwal' => $item,
            'jumlahSiswa' => (int) ($jumlahSiswa[$item->class_id] ?? 0),
            'sudahAbsen' => (int) ($tercatat[$item->id] ?? 0) > 0,
        ])->values();
    }

    /**
     * Jumlah kelas unik yang diajar pada tahun ajaran aktif. Dipakai untuk kartu
     * "Kelas Diampu" guru yang bukan wali kelas, menggantikan angka 0 yang
     * membingungkan pada "Kelas Diawasi".
     */
    private function jumlahKelasDiampu(User $user, AcademicYear $year): int
    {
        return $user->taughtSchedules()
            ->whereIn('class_id', SchoolClass::query()
                ->where('academic_year_id', $year->id)
                ->select('id'))
            ->distinct()
            ->count('class_id');
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
     * @return array{config: array<string, mixed>}
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
        ];
    }

    /**
     * @return array{config: array<string, mixed>}
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
