<?php

namespace Tests\Feature\Policy;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Policies\AcademicYearPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\SubjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DataMasterPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_boleh_kelola_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $policy = app(AcademicYearPolicy::class);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $tahun));
    }

    public function test_admin_boleh_kelola_mapel_kelas_dan_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create();

        $mataPelajaran = app(SubjectPolicy::class);
        $kelasPolicy = app(SchoolClassPolicy::class);
        $jadwalPolicy = app(SchedulePolicy::class);

        $this->assertTrue($mataPelajaran->viewAny($admin));
        $this->assertTrue($mataPelajaran->create($admin));
        $this->assertTrue($mataPelajaran->update($admin, $mapel));
        $this->assertTrue($mataPelajaran->delete($admin, $mapel));

        $this->assertTrue($kelasPolicy->viewAny($admin));
        $this->assertTrue($kelasPolicy->create($admin));
        $this->assertTrue($kelasPolicy->update($admin, $kelas));
        $this->assertTrue($kelasPolicy->delete($admin, $kelas));

        $this->assertTrue($jadwalPolicy->create($admin));
        $this->assertTrue($jadwalPolicy->update($admin, $jadwal));
        $this->assertTrue($jadwalPolicy->delete($admin, $jadwal));
    }

    public function test_guru_tidak_boleh_kelola_tahun_ajaran_dan_mapel(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = AcademicYear::factory()->create();
        $mapel = Subject::factory()->create();

        $tahunAjaran = app(AcademicYearPolicy::class);
        $mataPelajaran = app(SubjectPolicy::class);

        $this->assertFalse($tahunAjaran->viewAny($guru));
        $this->assertFalse($tahunAjaran->create($guru));
        $this->assertFalse($tahunAjaran->update($guru, $tahun));

        $this->assertFalse($mataPelajaran->viewAny($guru));
        $this->assertFalse($mataPelajaran->create($guru));
        $this->assertFalse($mataPelajaran->update($guru, $mapel));
        $this->assertFalse($mataPelajaran->delete($guru, $mapel));
    }

    public function test_guru_tidak_boleh_kelola_kelas_dan_jadwal(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create();

        $kelasPolicy = app(SchoolClassPolicy::class);
        $jadwalPolicy = app(SchedulePolicy::class);

        $this->assertFalse($kelasPolicy->viewAny($guru));
        $this->assertFalse($kelasPolicy->create($guru));
        $this->assertFalse($kelasPolicy->update($guru, $kelas));
        $this->assertFalse($kelasPolicy->delete($guru, $kelas));

        $this->assertFalse($jadwalPolicy->create($guru));
        $this->assertFalse($jadwalPolicy->update($guru, $jadwal));
        $this->assertFalse($jadwalPolicy->delete($guru, $jadwal));
    }

    public function test_daftar_jadwal_bisa_dilihat_semua_user(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwalOrangLain = Schedule::factory()->create();

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->viewAny($guru));
        $this->assertFalse($policy->view($guru, $jadwalOrangLain));
    }

    public function test_keempat_policy_data_master_terdaftar_di_gate(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        // Plan 1 hanya mendaftarkan UserPolicy secara eksplisit di AppServiceProvider.
        // Empat policy lain mengandalkan auto-discovery Laravel 11+. Kalau auto-discovery
        // itu mati, test yang memanggil app(Policy::class) tetap hijau padahal keenam
        // komponen akan 403 - jadi resolusi Gate wajib diuji lewat Gate, bukan policy.
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', AcademicYear::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Subject::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', SchoolClass::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Schedule::class));

        $this->assertFalse(Gate::forUser($guru)->allows('viewAny', AcademicYear::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', Subject::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', SchoolClass::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', Schedule::class));
    }
}
