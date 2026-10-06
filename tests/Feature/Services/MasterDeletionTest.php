<?php

namespace Tests\Feature\Services;

use App\Enums\HasilHapus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\MasterDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keputusan hapus permanen vs arsip.
 *
 * Aturannya satu: data hanya boleh hilang permanen dari MySQL kalau tidak ada
 * baris `attendances` yang merujuknya, langsung maupun lewat rantai relasi.
 * Kalau ada riwayat, barisnya ditandai deleted_at supaya riwayat absensi tidak
 * pernah ikut terhapus.
 *
 * Aturan ini juga yang membuat kita tidak pernah menabrak foreign key
 * `restrictOnDelete` di tabel attendances.
 */
class MasterDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function delesi(): MasterDeletion
    {
        return app(MasterDeletion::class);
    }

    // --- Mapel ----------------------------------------------------------

    public function test_mapel_tanpa_riwayat_dihapus_permanen(): void
    {
        $mapel = Subject::factory()->create();

        $hasil = $this->delesi()->hapus($mapel);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('subjects', ['id' => $mapel->id]);
    }

    public function test_mapel_dengan_riwayat_absensi_diarsipkan(): void
    {
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        $hasil = $this->delesi()->hapus($mapel);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('subjects', ['id' => $mapel->id]);
        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_mapel_dengan_jadwal_tanpa_absensi_dihapus_permanen_beserta_jadwalnya(): void
    {
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;

        $hasil = $this->delesi()->hapus($mapel);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('subjects', ['id' => $mapel->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $jadwal->id]);
    }

    // --- Siswa ----------------------------------------------------------

    public function test_siswa_tanpa_riwayat_dihapus_permanen(): void
    {
        $siswa = Student::factory()->create();

        $hasil = $this->delesi()->hapus($siswa);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('students', ['id' => $siswa->id]);
    }

    public function test_siswa_dengan_riwayat_diarsipkan(): void
    {
        $absensi = Attendance::factory()->create();
        $siswa = $absensi->student;

        $hasil = $this->delesi()->hapus($siswa);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('students', ['id' => $siswa->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    // --- Jadwal ---------------------------------------------------------

    public function test_jadwal_tanpa_riwayat_dihapus_permanen(): void
    {
        $jadwal = Schedule::factory()->create();

        $hasil = $this->delesi()->hapus($jadwal);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('schedules', ['id' => $jadwal->id]);
    }

    public function test_jadwal_dengan_riwayat_diarsipkan(): void
    {
        $absensi = Attendance::factory()->create();
        $jadwal = $absensi->schedule;

        $hasil = $this->delesi()->hapus($jadwal);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    // --- Kelas ----------------------------------------------------------

    public function test_kelas_tanpa_riwayat_dihapus_permanen(): void
    {
        $kelas = SchoolClass::factory()->create();

        $hasil = $this->delesi()->hapus($kelas);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('classes', ['id' => $kelas->id]);
    }

    public function test_kelas_berisi_siswa_tanpa_absensi_dihapus_permanen_beserta_siswanya(): void
    {
        $kelas = SchoolClass::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        $hasil = $this->delesi()->hapus($kelas);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('classes', ['id' => $kelas->id]);
        $this->assertDatabaseMissing('students', ['id' => $siswa->id]);
    }

    public function test_kelas_dengan_riwayat_absensi_siswanya_diarsipkan(): void
    {
        $absensi = Attendance::factory()->create();
        $siswa = $absensi->student;
        $kelas = $siswa->schoolClass;

        $hasil = $this->delesi()->hapus($kelas);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('classes', ['id' => $kelas->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    // --- Guru -----------------------------------------------------------

    public function test_guru_tanpa_riwayat_dihapus_permanen_beserta_jadwalnya(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $hasil = $this->delesi()->hapus($guru);

        $this->assertSame(HasilHapus::Permanen, $hasil);
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
        $this->assertDatabaseMissing('schedules', ['id' => $jadwal->id]);
    }

    public function test_guru_pencatat_absensi_diarsipkan_dan_jadwalnya_tetap_ada(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            'recorded_by' => $guru->id,
        ]);

        $hasil = $this->delesi()->hapus($guru);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('users', ['id' => $guru->id]);
        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_jadwal_guru_dicatat_guru_lain_tetap_menghalangi_penghapusan_permanen(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            // Pencatatnya guru lain, tapi absensinya tetap menempel pada
            // jadwal milik guru ini.
            'recorded_by' => User::factory()->guru()->create()->id,
        ]);

        $hasil = $this->delesi()->hapus($guru);

        $this->assertSame(HasilHapus::Diarsipkan, $hasil);
        $this->assertSoftDeleted('users', ['id' => $guru->id]);
        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }
}
