<?php

namespace Tests\Feature\Policy;

use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mengelola_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = Student::factory()->create();
        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($admin, $siswa));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $siswa));
        $this->assertTrue($policy->delete($admin, $siswa));
    }

    public function test_guru_hanya_baca_siswa_kelas_yang_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Student::factory()->create();

        Schedule::factory()->create([
            'class_id' => $siswa->class_id,
            'teacher_id' => $guru->id,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($guru, $siswa));
        $this->assertFalse($policy->update($guru, $siswa));
        $this->assertFalse($policy->delete($guru, $siswa));
        $this->assertFalse($policy->create($guru));
    }

    public function test_guru_tidak_lihat_siswa_kelas_yang_tidak_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Student::factory()->create();

        $this->assertFalse(app(StudentPolicy::class)->view($guru, $siswa));
    }

    public function test_view_tetap_berjalan_saat_siswa_diambil_dari_daftar_banyak_baris(): void
    {
        $guru = User::factory()->guru()->create();
        $siswaDiajar = Student::factory()->create();
        $siswaTidakDiajar = Student::factory()->create();

        Schedule::factory()->create([
            'class_id' => $siswaDiajar->class_id,
            'teacher_id' => $guru->id,
        ]);

        // Daftar siswa untuk absensi atau rekap menghasilkan banyak baris, dan
        // Eloquent hanya mengaktifkan guard lazy loading kalau baris lebih dari
        // satu. Policy harus tahan terhadap kedua bentuk pemanggilan.
        $dariDaftar = Student::query()
            ->whereIn('id', [$siswaDiajar->id, $siswaTidakDiajar->id])
            ->get();

        $this->assertTrue($dariDaftar->first()->preventsLazyLoading, 'Test ini harus lewat jalur yang sama dengan pemakaian nyata.');

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($guru, $dariDaftar->firstWhere('id', $siswaDiajar->id)));
        $this->assertFalse($policy->view($guru, $dariDaftar->firstWhere('id', $siswaTidakDiajar->id)));
    }

    public function test_view_any_menentukan_munculnya_menu_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $guruTanpaJadwal = User::factory()->guru()->create();
        $guruDenganJadwal = User::factory()->guru()->create();

        Schedule::factory()->create(['teacher_id' => $guruDenganJadwal->id]);

        $policy = app(StudentPolicy::class);

        // viewAny tidak diuji plan mana pun, padahal Plan 3 dan Plan 4 memakainya
        // untuk menentukan apakah menu siswa tampil.
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($guruDenganJadwal));
        $this->assertFalse($policy->viewAny($guruTanpaJadwal));
    }
}
