<?php

namespace Tests\Feature\Policy;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use App\Policies\SchoolClassPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_rekap_semua_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();

        $this->assertTrue(app(SchoolClassPolicy::class)->viewRecap($admin, $kelas));
    }

    public function test_wali_kelas_melihat_kelasnya(): void
    {
        $wali = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['homeroom_teacher_id' => $wali->id]);

        $this->assertTrue(app(SchoolClassPolicy::class)->viewRecap($wali, $kelas));
    }

    public function test_guru_biasa_tidak_boleh_lihat_rekap_kelas(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create();

        $this->assertFalse(app(SchoolClassPolicy::class)->viewRecap($guru, $kelas));
    }

    public function test_wali_kelas_tidak_boleh_lihat_kelas_lain(): void
    {
        $wali = User::factory()->guru()->create();
        $kelasLain = SchoolClass::factory()->create();

        $this->assertFalse(app(SchoolClassPolicy::class)->viewRecap($wali, $kelasLain));
    }

    /**
     * Kelas dibuat ulang tiap tahun, jadi wali yang sama punya baris `classes`
     * yang berbeda untuk ganjil dan genap. Hanya membandingkan
     * `homeroom_teacher_id` membuat kelas dari tahun lalu tetap lolos, dan
     * `HomeroomClassRecapQuery::forClass()` lalu melempar exception yang
     * menjadi 500 - bukan 403 yang bisa dipahami.
     *
     * Diuji di policy, bukan di component: harness pengujian Livewire mengubah
     * exception apa pun menjadi status 403, jadi `assertForbidden()` di level
     * component akan hijau baik untuk penolakan yang benar maupun untuk
     * exception yang tidak sengaja.
     */
    public function test_wali_kelas_tidak_boleh_lihat_kelasnya_dari_tahun_ajaran_lain(): void
    {
        $wali = User::factory()->guru()->create();

        $tahunIni = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);

        $kelasLalu = SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'homeroom_teacher_id' => $wali->id,
        ]);

        $policy = app(SchoolClassPolicy::class);

        $this->assertFalse($policy->viewRecap($wali, $kelasLalu, $tahunIni));
        $this->assertTrue(
            $policy->viewRecap($wali, $kelasLalu, $tahunLalu),
            'Kelas tetap boleh dibuka ketika tahun_subset yang dipilih memang pemilik kelasnya.',
        );

        // Tanpa tahun_subset, ability ini memakai aturan lama tanpa-check.
        $this->assertTrue($policy->viewRecap($wali, $kelasLalu));
    }

    /**
     * Admin bebas memilih kelas mana pun, tapi tetap hanya kelas milik tahun
     * ajaran yang sedang ditampilkan: `HomeroomClassRecapQuery::ownedClasses()`
     * menyaring `academic_year_id` tanpa membeda-bedakan role, jadi tahun yang
     * diperlonggarkan hanya untuk admin akan membuat `classId` dari tahun lain
     * lolos policy lalu ditolak query sebagai 500.
     */
    public function test_admin_tetap_dibatasi_tahun_ajaran_untuk_membuka_kelas(): void
    {
        $admin = User::factory()->admin()->create();

        $tahunIni = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);

        $kelasLalu = SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'homeroom_teacher_id' => null,
        ]);
        $kelasSekarang = SchoolClass::factory()->create([
            'academic_year_id' => $tahunIni->id,
            'homeroom_teacher_id' => null,
        ]);

        $policy = app(SchoolClassPolicy::class);

        $this->assertTrue($policy->viewRecap($admin, $kelasSekarang, $tahunIni));
        $this->assertFalse($policy->viewRecap($admin, $kelasLalu, $tahunIni));
        $this->assertTrue($policy->viewRecap($admin, $kelasLalu, $tahunLalu));
    }
}
