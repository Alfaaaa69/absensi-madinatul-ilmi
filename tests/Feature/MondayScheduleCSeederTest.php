<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\MondayScheduleCSeeder;
use Database\Seeders\MondayScheduleSeeder;
use Database\Seeders\SchoolTimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MondayScheduleCSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_jadwal_senin_kelas_c_tanpa_master_baru(): void
    {
        $this->seed(SchoolTimetableSeeder::class);
        $this->seed(MondayScheduleSeeder::class);

        $jumlahMapel = Subject::count();
        $jumlahGuru = User::where('role', UserRole::Guru)->count();

        $this->seed(MondayScheduleCSeeder::class);
        $this->seed(MondayScheduleCSeeder::class);

        $rows = $this->cRows();

        $this->assertSame(27, $rows->count());
        $this->assertSame($jumlahMapel, Subject::count());
        $this->assertSame($jumlahGuru, User::where('role', UserRole::Guru)->count());
        $this->assertSame(9, $rows->countBy('class_id')->unique()->sole());
    }

    public function test_tidak_ada_bentrok_guru_di_hari_senin(): void
    {
        $this->seed(SchoolTimetableSeeder::class);
        $this->seed(MondayScheduleSeeder::class);
        $this->seed(MondayScheduleCSeeder::class);

        $cRows = $this->cRows();
        $allMonday = Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->with(['schoolClass', 'subject', 'teacher'])
            ->get()
            ->values();

        $bentrok = 0;

        for ($a = 0; $a < $allMonday->count(); $a++) {
            for ($b = $a + 1; $b < $allMonday->count(); $b++) {
                $satu = $allMonday[$a];
                $dua = $allMonday[$b];

                if ($satu->teacher_id === $dua->teacher_id
                    && $satu->start_time < $dua->end_time
                    && $dua->start_time < $satu->end_time) {
                    $bentrok++;
                }
            }
        }

        $this->assertSame(0, $bentrok, 'Guru tidak boleh mengajar dua kelas pada jam yang sama.');

        $bentrokKelas = 0;

        foreach ($cRows->groupBy('class_id') as $rows) {
            $rows = $rows->values();

            for ($a = 0; $a < $rows->count(); $a++) {
                for ($b = $a + 1; $b < $rows->count(); $b++) {
                    if ($rows[$a]->start_time < $rows[$b]->end_time && $rows[$b]->start_time < $rows[$a]->end_time) {
                        $bentrokKelas++;
                    }
                }
            }
        }

        $this->assertSame(0, $bentrokKelas, 'Satu kelas tidak boleh punya dua mapel pada jam yang sama.');
    }

    public function test_semua_guru_sudah_mengajar_mapel_tersebut(): void
    {
        $this->seed(SchoolTimetableSeeder::class);
        $this->seed(MondayScheduleSeeder::class);
        $this->seed(MondayScheduleCSeeder::class);

        $cClassIds = SchoolClass::whereIn('name', ['7-C', '8-C', '9-C'])->pluck('id');

        $pasanganExisting = Schedule::withTrashed()
            ->whereNotIn('class_id', $cClassIds)
            ->get()
            ->map(fn (Schedule $schedule) => $schedule->teacher_id.'|'.$schedule->subject_id)
            ->unique();

        $tidakSesuai = $this->cRows()->reject(
            fn (Schedule $schedule) => $pasanganExisting->contains($schedule->teacher_id.'|'.$schedule->subject_id)
        );

        $this->assertSame(
            [],
            $tidakSesuai->map(fn (Schedule $schedule) => $schedule->teacher->name.' - '.$schedule->subject->name)->values()->all(),
            'Guru kelas C harus guru yang sudah mengajar mapel tersebut.'
        );
    }

    private function cRows()
    {
        $cClassIds = SchoolClass::whereIn('name', ['7-C', '8-C', '9-C'])->pluck('id');

        return Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->whereIn('class_id', $cClassIds)
            ->with(['schoolClass', 'subject', 'teacher'])
            ->get();
    }
}
