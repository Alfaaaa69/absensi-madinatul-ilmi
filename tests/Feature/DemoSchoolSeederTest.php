<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\RecapPeriod;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSchoolSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menghasilkan_data_lengkap(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertSame(10, User::where('role', UserRole::Guru)->count());
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
        $this->assertSame(7, SchoolClass::count());
        $this->assertSame(210, Student::count());
        $this->assertSame(8, Subject::count());
        $this->assertSame(56, Schedule::count());
        $this->assertGreaterThan(0, Attendance::count());
    }

    public function test_setiap_kelas_punya_wali_kelas(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(0, SchoolClass::whereNull('homeroom_teacher_id')->count());
    }

    public function test_nisn_unik_seluruh_sekolah(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(
            Student::count(),
            Student::distinct()->count('nisn'),
            'NISN harus unik global, bukan per kelas'
        );
    }

    public function test_tidak_ada_jadwal_bentrok(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $bentrok = 0;

        foreach (Schedule::all()->groupBy(fn (Schedule $j) => $j->teacher_id.'|'.$j->day_of_week->value) as $grup) {
            $grup = $grup->values();

            for ($a = 0; $a < $grup->count(); $a++) {
                for ($b = $a + 1; $b < $grup->count(); $b++) {
                    $satu = $grup[$a];
                    $dua = $grup[$b];

                    if ($satu->start_time < $dua->end_time && $dua->start_time < $satu->end_time) {
                        $bentrok++;
                    }
                }
            }
        }

        $this->assertSame(0, $bentrok, 'Seeder tidak boleh menghasilkan jadwal bentrok');
    }

    public function test_absensi_hanya_dibuat_di_hari_jadwalnya(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $salah = 0;

        DB::table('attendances')
            ->join('schedules', 'schedules.id', '=', 'attendances.schedule_id')
            ->select('attendances.date', 'schedules.day_of_week')
            // each() menolak query tanpa ORDER BY karena hasil harus stabil
            // antar chunk.
            ->orderBy('attendances.id')
            ->each(function (object $row): void {
                // Carbon::dayOfWeek 0=Minggu..6=Sabtu berlaku sama di MySQL
                // dan SQLite. Fungsi SQL weekday() tidak dipakai: penomorannya
                // Sunday-first dan tidak sama dengan schedules.day_of_week.
                if (Carbon::parse($row->date)->dayOfWeek !== (int) $row->day_of_week) {
                    $salah++;
                }
            });

        $this->assertSame(0, $salah, 'Absensi hanya boleh ada di hari jadwalnya');
    }

    public function test_tahun_ajaran_aktif_mencakup_hari_ini(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $tahun = AcademicYear::where('is_active', true)->firstOrFail();

        $this->assertTrue(now()->between(
            $tahun->start_date->copy()->startOfDay(),
            $tahun->end_date->copy()->endOfDay()
        ));
    }

    public function test_periode_aktif_bisa_dipakai_untuk_rekap(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $tahun = AcademicYear::where('is_active', true)->firstOrFail();
        $periode = RecapPeriod::monthly(now()->year, now()->month);

        $this->assertTrue($periode->isWithin($tahun));
    }

    public function test_setiap_guru_maksimal_mengajar_tiga_kelas(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        foreach (User::where('role', UserRole::Guru)->get() as $guru) {
            $jumlahKelas = $guru->taughtSchedules()->distinct()->count('class_id');
            $this->assertLessThanOrEqual(3, $jumlahKelas, "Guru {$guru->name} mengajar {$jumlahKelas} kelas (maksimal 3)");
        }
    }
}
