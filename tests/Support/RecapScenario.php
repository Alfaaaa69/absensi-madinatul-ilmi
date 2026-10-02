<?php

namespace Tests\Support;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Tahun ajaran aktif, wali kelas yang juga mengajar, satu kelas, satu siswa, satu jadwal
 * Matematika, dua absensi September.
 */
final class RecapScenario
{
    public static function waliKelas(): object
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));

        $tahun = AcademicYear::factory()->create([
            'name' => '2025/2026',
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
        $wali = User::factory()->guru()->create(['name' => 'Budi Santoso']);
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
            'name' => 'VII-A',
        ]);
        $siswa = self::siswaDi($kelas, 'Ahmad Fauzi');
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika'])->id,
            'teacher_id' => $wali->id,
        ]);
        self::absen($siswa, $jadwal, '2025-09-01', AttendanceStatus::Hadir);
        self::absen($siswa, $jadwal, '2025-09-02', AttendanceStatus::Izin, 'Surat dokter');

        return (object) compact('tahun', 'wali', 'kelas', 'siswa', 'jadwal');
    }

    public static function kelasLain(object $skenario, string $nama, User $wali): SchoolClass
    {
        return SchoolClass::factory()->create([
            'academic_year_id' => $skenario->tahun->id,
            'homeroom_teacher_id' => $wali->id,
            'name' => $nama,
        ]);
    }

    public static function siswaDi(SchoolClass $kelas, string $nama): Student
    {
        return Student::factory()->create(['class_id' => $kelas->id, 'name' => $nama]);
    }

    public static function mapelLain(object $skenario, string $nama, User $guru): Schedule
    {
        return Schedule::factory()->create([
            'class_id' => $skenario->kelas->id,
            'subject_id' => Subject::factory()->create(['name' => $nama])->id,
            'teacher_id' => $guru->id,
        ]);
    }

    public static function absen(
        Student $siswa,
        Schedule $jadwal,
        string $tanggal,
        AttendanceStatus $status,
        ?string $catatan = null,
        ?User $oleh = null,
    ): Attendance {
        return Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => $tanggal,
            'status' => $status,
            'note' => $catatan,
            'recorded_by' => $oleh?->id ?? $jadwal->teacher_id,
        ]);
    }
}
