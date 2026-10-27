<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\MondayScheduleSeeder;
use Database\Seeders\SchoolTimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MondayScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_melengkapi_mapel_dan_guru_lalu_mengisi_seluruh_jadwal_senin(): void
    {
        $this->seed(SchoolTimetableSeeder::class);

        $jumlahMapel = Subject::count();
        $jumlahGuru = User::where('role', UserRole::Guru)->count();

        $this->seed(MondayScheduleSeeder::class);
        $this->seed(MondayScheduleSeeder::class);

        $kelas = SchoolClass::whereIn('name', ['7-A', '7-B', '8-A', '8-B', '9-A', '9-B'])->pluck('id');
        $senin = Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->whereIn('class_id', $kelas);

        $this->assertSame(54, (clone $senin)->count());
        $this->assertSame($jumlahMapel + 7, Subject::count());
        $this->assertSame($jumlahGuru + 7, User::where('role', UserRole::Guru)->count());
        $this->assertSame(0, (clone $senin)
            ->get()
            ->groupBy(fn (Schedule $schedule) => $schedule->class_id.'|'.$schedule->start_time)
            ->filter(fn ($rows) => $rows->count() > 1)
            ->count());

        foreach ([
            'Bahasa Jawa',
            'Literasi',
            'Bimbingan Konseling',
            'Prakarya',
            'Seni & Prakarya',
            'Ekstra. Ketr. Ukir',
            'Ekstra. Science',
        ] as $mapel) {
            $this->assertDatabaseHas('subjects', ['name' => $mapel]);
        }
    }

    public function test_guru_mengikuti_jadwal_existing_dan_mapel_baru_memakai_guru_baru(): void
    {
        $this->seed(SchoolTimetableSeeder::class);
        $this->seed(MondayScheduleSeeder::class);

        $this->assertSame(
            'Dr. Hj. Siti Aminah',
            $this->teacherFor('7-A', 'Ilmu Pengetahuan Alam', '07:45:00')
        );
        $this->assertSame(
            'Rina Marlina, S.Pd.',
            $this->teacherFor('7-B', 'Matematika', '11:10:00')
        );
        $this->assertSame(
            'Lestari Wulandari, S.Pd.',
            $this->teacherFor('9-B', 'Seni Budaya', '11:45:00')
        );
        $this->assertSame(
            'Bagus Setyo Nugroho, S.Pd.',
            $this->teacherFor('7-A', 'Bahasa Jawa', '09:30:00')
        );
        $this->assertSame(
            'Dian Permata Sari, S.Psi.',
            $this->teacherFor('9-B', 'Bimbingan Konseling', '08:55:00')
        );
    }

    private function teacherFor(string $className, string $subjectName, string $startTime): ?string
    {
        return Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->whereTime('start_time', $startTime)
            ->whereHas('schoolClass', fn ($query) => $query->where('name', $className))
            ->whereHas('subject', fn ($query) => $query->where('name', $subjectName))
            ->first()
            ?->teacher
            ?->name;
    }
}
