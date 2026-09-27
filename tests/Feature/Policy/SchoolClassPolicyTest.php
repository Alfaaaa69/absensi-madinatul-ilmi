<?php

namespace Tests\Feature\Policy;

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
}
