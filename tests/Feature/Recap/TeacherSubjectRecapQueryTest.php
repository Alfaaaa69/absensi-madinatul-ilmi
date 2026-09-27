<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\Queries\TeacherSubjectRecapQuery;
use App\Recap\RecapPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSubjectRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_mapel_guru_lain_tidak_masuk_dari_kelas_yang_sama(): void
    {
        $guru = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $mapelGuru = Subject::factory()->create();
        $mapelLain = Subject::factory()->create();

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelGuru->id,
            'teacher_id' => $guru->id,
        ]);
        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelLain->id,
            'teacher_id' => $guruLain->id,
        ]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertCount(1, $hasil->subjects);
        $this->assertSame($mapelGuru->id, $hasil->subjects->first()->id);
    }

    public function test_guru_mengajar_dua_kelas_menghasilkan_satu_baris_per_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelasA = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $kelasB = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $mapel = Subject::factory()->create();

        Schedule::factory()->create(['class_id' => $kelasA->id, 'subject_id' => $mapel->id, 'teacher_id' => $guru->id]);
        Schedule::factory()->create(['class_id' => $kelasB->id, 'subject_id' => $mapel->id, 'teacher_id' => $guru->id]);

        Student::factory()->create(['class_id' => $kelasA->id]);
        Student::factory()->create(['class_id' => $kelasB->id]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(2, $hasil->rows);
        $this->assertCount(2, $hasil->classes);
    }

    public function test_guru_tidak_lihat_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $guruLain->id,
        ]);
        Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::create([
            'student_id' => Student::first()->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-09-01',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guruLain->id,
        ]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(0, $hasil->rows);
        $this->assertCount(0, $hasil->classes);
    }

    public function test_guru_tanpa_jadwal_tidak_melihat_apapun(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = $this->tahun();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(0, $hasil->classes);
        $this->assertCount(0, $hasil->subjects);
        $this->assertCount(0, $hasil->rows);
    }
}
