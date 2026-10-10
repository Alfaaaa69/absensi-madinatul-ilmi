<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\StudentGender;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', $kelas->id)
            ->set('nisn', '3200123456')
            ->set('name', 'Budi Santoso')
            ->set('gender', StudentGender::L->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'class_id' => $kelas->id,
            'nisn' => '3200123456',
            'name' => 'Budi Santoso',
            'gender' => 'L',
        ]);
    }

    public function test_nisn_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        Student::factory()->create(['nisn' => '3200123456']);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', $kelas->id)
            ->set('nisn', '3200123456')
            ->set('name', 'Budi Santoso')
            ->set('gender', StudentGender::L->value)
            ->call('save')
            ->assertHasErrors(['nisn']);

        $this->assertSame(1, Student::count());
    }

    public function test_kelas_dan_jenis_kelamin_wajib_diisi(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', '')
            ->set('nisn', '3200999999')
            ->set('name', 'Sari Wulandari')
            ->set('gender', 'X')
            ->call('save')
            ->assertHasErrors(['class_id', 'gender']);

        $this->assertSame(0, Student::count());
    }

    public function test_siswa_diarsipkan_riwayat_absensinya_tetap_utuh(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->call('askDelete', $siswa->id)
            ->call('confirmDelete')
            ->assertSee('punya riwayat absensi');

        $this->assertSoftDeleted('students', ['id' => $siswa->id]);
        $this->assertFalse(Student::query()->whereKey($siswa->id)->exists());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_siswa_tanpa_riwayat_dihapus_permanen(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = Student::factory()->create(['nisn' => '3200123456']);

        Livewire::actingAs($admin)->test(StudentManagement::class)->call('askDelete', $siswa->id)->call('confirmDelete');

        $this->assertDatabaseMissing('students', ['id' => $siswa->id]);
    }

    public function test_siswa_yang_dipakai_absensi_menampilkan_tombol_arsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        // Attendance::factory() selalu menulis jadwal+siswa cadangan ke DB,
        // jadi catatan absensi dibuat langsung supaya halaman hanya punya satu baris.
        Attendance::query()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $jadwal->teacher_id,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->assertSee('Arsipkan')
            ->assertDontSee('Hapus');
    }

    public function test_siswa_tanpa_riwayat_menampilkan_tombol_hapus(): void
    {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['nisn' => '3200123456']);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->assertSee('Hapus')
            ->assertDontSee('Arsipkan');
    }

    public function test_konfirmasi_hapus_siswa_berriwayat_menjelaskan_arsip_sebelum_aksi(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id, 'name' => 'Ririn']);

        Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->call('askDelete', $siswa->id)
            ->assertSet('pendingDeletePermanent', false)
            ->assertSet('pendingDeleteMessage', 'Ririn diarsipkan, bukan dihapus permanen. Siswa ini punya riwayat absensi yang harus tetap bisa dibaca.');

        $this->assertDatabaseHas('students', ['id' => $siswa->id, 'deleted_at' => null]);
    }

    public function test_siswa_arsip_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = Student::factory()->create(['nisn' => '3200123456']);
        $siswa->delete();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('showArchived', true)
            ->call('pulihkan', $siswa->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('students', ['id' => $siswa->id]);
    }

    public function test_pencarian_dan_filter_kelas_dan_jenis_kelamin(): void
    {
        $admin = User::factory()->admin()->create();
        $kelasA = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelasB = SchoolClass::factory()->create(['name' => 'VII-B']);

        Student::factory()->create([
            'class_id' => $kelasA->id,
            'nisn' => '1001',
            'name' => 'Budi Santoso',
            'gender' => StudentGender::L,
        ]);
        Student::factory()->create([
            'class_id' => $kelasB->id,
            'nisn' => '1002',
            'name' => 'Sari Wulandari',
            'gender' => StudentGender::P,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Sari Wulandari')
            ->set('search', '1002')
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso')
            ->set('search', '')
            ->set('filterClassId', $kelasB->id)
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso')
            ->set('filterClassId', '')
            ->set('filterGender', StudentGender::P->value)
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso');
    }

    public function test_siswa_naik_kelas_menyalin_nisn_tetap_valid(): void
    {
        $admin = User::factory()->admin()->create();
        $kelasA = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelasB = SchoolClass::factory()->create(['name' => 'VIII-A']);
        $siswa = Student::factory()->create([
            'class_id' => $kelasA->id,
            'nisn' => '3200123456',
            'name' => 'Budi Santoso',
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->call('editForm', $siswa->id)
            ->set('class_id', $kelasB->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'id' => $siswa->id,
            'class_id' => $kelasB->id,
            'nisn' => '3200123456',
        ]);
    }

    public function test_daftar_kelas_menampilkan_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create(['name' => '2025/2026']);
        SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->assertSee('VII-A')
            ->assertSee('2025/2026');
    }

    public function test_siswa_yang_kelasnya_diarsipkan_tetap_menampilkan_nama_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        Student::factory()->create([
            'class_id' => $kelas->id,
            'name' => 'Ahmad Fauzi',
        ]);

        // Kelas diarsipkan, tapi siswa dan riwayat absensinya tetap harus tampil.
        $kelas->delete();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->assertOk()
            ->assertSee('Ahmad Fauzi')
            ->assertSee('VII-A');
    }

    public function test_guru_tidak_bisa_membuka_halaman_siswa(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/siswa')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_siswa(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/siswa')
            ->assertOk()
            ->assertSee('Siswa');
    }
}
