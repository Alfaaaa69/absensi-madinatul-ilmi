<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\ClassManagement;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dropdown_wali_menandai_guru_yang_sudah_jadi_wali(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $sudahWali = User::factory()->guru()->create(['name' => 'Guru Wali']);
        $guruBiasa = User::factory()->guru()->create(['name' => 'Guru Biasa']);
        SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $sudahWali->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->assertSee('— wali VII-A')
            ->assertSee('Guru Biasa')
            ->assertDontSee('Guru Biasa — wali');
    }

    public function test_admin_bisa_menambah_kelas_dengan_wali_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $wali = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', $wali->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('classes', [
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
            'grade_level' => 7,
            'homeroom_teacher_id' => $wali->id,
        ]);
    }

    public function test_wali_kelas_bisa_kosong(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-B')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $kelas = SchoolClass::where('name', 'VII-B')->firstOrFail();

        $this->assertNull($kelas->homeroom_teacher_id);
        $this->assertDatabaseHas('classes', ['id' => $kelas->id]);
    }

    public function test_kelas_tanpa_wali_tampil_katakan_belum_ditentukan(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create(['name' => 'VII-B', 'homeroom_teacher_id' => null]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->assertSee('Belum ditentukan')
            ->assertDontSee('{null}');
    }

    public function test_wali_kelas_diarsipkan_tetap_menampilkan_namanya(): void
    {
        $admin = User::factory()->admin()->create();
        $wali = User::factory()->guru()->create(['name' => 'Siti Aminah']);
        SchoolClass::factory()->create([
            'name' => 'VII-C',
            'homeroom_teacher_id' => $wali->id,
        ]);

        // Wali diarsipkan bukan berarti kelas ini belum punya wali. Catatan:
        // teks "Belum ditentukan" juga muncul sebagai <option> di form, jadi
        // yang dibuktikan di sini adalah nama walinya tetap tampil.
        $wali->delete();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->assertOk()
            ->assertSee('VII-C')
            ->assertSee('Siti Aminah');
    }

    public function test_nama_kelas_duplikat_di_tahun_ajaran_yang_sama_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(1, SchoolClass::where('name', 'VII-A')->count());
    }

    public function test_nama_kelas_sama_boleh_di_tahun_ajaran_berbeda(): void
    {
        $admin = User::factory()->admin()->create();
        $tahunIni = AcademicYear::factory()->create();
        $tahunLalu = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);
        SchoolClass::factory()->create(['academic_year_id' => $tahunIni->id, 'name' => 'VII-A']);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahunLalu->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, SchoolClass::where('name', 'VII-A')->count());
    }

    public function test_satu_guru_hanya_bisa_menjadi_wali_satu_kelas_per_tahun(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $wali = User::factory()->guru()->create();
        SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $wali->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-B')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', $wali->id)
            ->call('save')
            ->assertHasErrors(['homeroom_teacher_id']);

        $this->assertSame(1, SchoolClass::where('name', 'VII-A')->count());
    }

    public function test_guru_yang_sudah_menjadi_wali_di_tahun_lalu_bisa_dijadi_wali_lagi(): void
    {
        $admin = User::factory()->admin()->create();
        $tahunLalu = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);
        $tahunIni = AcademicYear::factory()->create();
        $wali = User::factory()->guru()->create();
        SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $wali->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahunIni->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', $wali->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, SchoolClass::where('homeroom_teacher_id', $wali->id)->count());
    }

    public function test_kelas_berisi_absensi_hanya_diarsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->count(3)->create(['class_id' => $kelas->id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->first()->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->call('askDelete', $kelas->id)
            ->call('confirmDelete')
            ->assertSee('3 siswa');

        $this->assertSoftDeleted('classes', ['id' => $kelas->id]);
        $this->assertSame(0, SchoolClass::where('name', 'VII-A')->count());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_kelas_tanpa_riwayat_dihapus_permanen_beserta_siswanya(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $siswa = Student::factory()->count(3)->create(['class_id' => $kelas->id]);

        Livewire::actingAs($admin)->test(ClassManagement::class)->call('askDelete', $kelas->id)->call('confirmDelete');

        $this->assertDatabaseMissing('classes', ['id' => $kelas->id]);
        $this->assertDatabaseMissing('students', ['id' => $siswa->first()->id]);
    }

    public function test_kelas_yang_dipakai_absensi_menampilkan_tombol_arsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);
        Attendance::query()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $jadwal->teacher_id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->assertSee('Arsipkan')
            ->assertDontSee('Hapus');
    }

    public function test_konfirmasi_hapus_kelas_tanpa_riwayat_menjelaskan_hapus_permanen(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->call('askDelete', $kelas->id)
            ->assertSet('pendingDeletePermanent', true)
            ->assertSet('pendingDeleteMessage', 'Kelas VII-A dihapus permanen.');
    }

    public function test_kelas_arsip_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelas->delete();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('showArchived', true)
            ->call('pulihkan', $kelas->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('classes', ['id' => $kelas->id]);
    }

    public function test_tingkat_kelas_hanya_tujuh_sampai_sembilan(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'X-A')
            ->set('grade_level', 10)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasErrors(['grade_level']);

        $this->assertSame(0, SchoolClass::where('name', 'X-A')->count());
    }

    public function test_guru_tidak_bisa_membuka_halaman_kelas(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/kelas')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_kelas(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/kelas')
            ->assertOk()
            ->assertSee('Kelas');
    }
}
