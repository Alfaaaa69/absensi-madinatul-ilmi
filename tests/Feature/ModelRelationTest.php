<?php

namespace Tests\Feature;

use App\Enums\AcademicPeriod;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\StudentGender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModelRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_ditemukan_lewat_relasi_bukan_role(): void
    {
        $wali = User::factory()->create(['role' => UserRole::Guru]);
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);

        $this->assertSame(UserRole::Guru, $wali->fresh()->role);
        $this->assertTrue($wali->isHomeroomOf($kelas));
        $this->assertTrue($wali->homeroomClasses->contains($kelas));
    }

    public function test_schedule_belajar_dari_kelas_subject_dan_guru(): void
    {
        $jadwal = Schedule::factory()->create([
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
        ]);

        $this->assertInstanceOf(SchoolClass::class, $jadwal->schoolClass);
        $this->assertInstanceOf(Subject::class, $jadwal->subject);
        $this->assertInstanceOf(User::class, $jadwal->teacher);
        $this->assertSame(DayOfWeek::Senin, $jadwal->day_of_week);
    }

    public function test_attendance_menyimpan_recorder_dan_updater(): void
    {
        $guru = User::factory()->create();
        $absensi = Attendance::factory()->create([
            'recorded_by' => $guru->id,
            'updated_by' => null,
        ]);

        $this->assertTrue($absensi->recorder->is($guru));
        $this->assertNull($absensi->updater);
    }

    public function test_enum_di_cast_otomatis(): void
    {
        $siswa = Student::factory()->create(['gender' => StudentGender::P]);
        $absensi = Attendance::factory()->create(['status' => AttendanceStatus::Sakit]);
        $tahun = AcademicYear::factory()->create(['period' => AcademicPeriod::Genap]);
        $pengguna = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame(StudentGender::P, $siswa->fresh()->gender);
        $this->assertSame(AttendanceStatus::Sakit, $absensi->fresh()->status);
        $this->assertSame(AcademicPeriod::Genap, $tahun->fresh()->period);
        $this->assertSame(UserRole::Admin, $pengguna->fresh()->role);
    }

    public function test_hanya_tabel_master_yang_pakai_soft_delete(): void
    {
        // Plan menyebut assertContainsUses()/assertNotContainsUses(); method itu tidak
        // ada di Laravel 13 maupun PHPUnit. class_uses_recursive() adalah helper resmi
        // Laravel (Illuminate/Support/helpers.php) yang setara dan kita pakai gantinya.
        $this->assertContains(SoftDeletes::class, \class_uses_recursive(new SchoolClass));
        $this->assertContains(SoftDeletes::class, \class_uses_recursive(new Student));
        $this->assertContains(SoftDeletes::class, \class_uses_recursive(new Schedule));
        $this->assertNotContains(SoftDeletes::class, \class_uses_recursive(new Attendance));
        $this->assertNotContains(SoftDeletes::class, \class_uses_recursive(new AcademicYear));
    }

    public function test_walayah_grade_level_tervalidasi(): void
    {
        $this->expectException(ValidationException::class);

        SchoolClass::factory()->create(['grade_level' => 12]);
    }

    public function test_hapus_siswa_tidak_menghapus_absensi(): void
    {
        $absensi = Attendance::factory()->create();
        $absensi->student->delete();

        $this->assertTrue($absensi->student->trashed());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }
}
