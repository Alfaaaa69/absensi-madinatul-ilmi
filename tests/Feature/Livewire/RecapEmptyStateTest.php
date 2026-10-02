<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminAttendanceReport;
use App\Livewire\HomeroomClassRecap;
use App\Livewire\TeacherSubjectRecap;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class RecapEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tahun_ajaran_kosong_di_kedua_halaman(): void
    {
        // Tidak ada skenario sama sekali: "belum ada tahun ajaran" berarti
        // tidak ada satu pun baris di database. Menghapus tahun ajaran
        // setelah kelas dibuat tidak bisa dilakukan, karena classes.academic_year_id
        // punya foreign key.
        Livewire::actingAs(User::factory()->guru()->create())->test(HomeroomClassRecap::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.')
            ->assertDontSee('Tidak ada data absensi pada periode ini.');

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.');
    }

    public function test_wali_belum_diwalikan_kelas(): void
    {
        RecapScenario::waliKelas();
        $guru = User::factory()->guru()->create();

        Livewire::actingAs($guru)->test(HomeroomClassRecap::class)
            ->assertSee('Kamu belum diwalikan kelas. Hubungi admin.');
    }

    public function test_guru_belum_punya_jadwal(): void
    {
        RecapScenario::waliKelas();

        // attendances dulu: attendances.schedule_id menunjuk schedules.id.
        Attendance::query()->delete();
        Schedule::query()->delete();

        Livewire::actingAs(User::factory()->guru()->create())->test(TeacherSubjectRecap::class)
            ->assertSee('Belum ada jadwal mengajar pada tahun ajaran ini.');
    }

    public function test_admin_belum_ada_kelas(): void
    {
        // Hanya tahun ajaran yang ada, tanpa kelas sama sekali. Menghapus
        // jadwal tidak akan cukup: kelasnya masih ada dan tabelnya tetap
        // punya kolom.
        Carbon::setTestNow(Carbon::parse('2025-09-15'));
        AcademicYear::factory()->create([
            'name' => '2025/2026',
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Tidak ada kelas pada tahun ajaran ini.');
    }

    public function test_kelas_ada_tapi_absensi_kosong(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');
    }

    public function test_guru_dan_admin_juga_melihat_pesan_absensi_kosong(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();

        // Actors-nya harus orang yang benar-benar mengajar di skenario ini.
        // Guru yang tidak punya jadwal akan melihat pesan "belum ada jadwal",
        // bukan pesan absensi kosong - itu kondisi kosong yang berbeda.
        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');
    }

    /**
     * Angka nol yang tampil harus tetap terlihat saat tabel kosong. Kalau
     * disembunyikan, pembaca tidak bisa membedakan "tidak ada data" dari
     * "semuanya nol", padahal keduanya berbeda.
     */
    public function test_matriks_kosong_menampilkan_empat_kolom_ringkasan(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Hadir 0')->assertSee('Izin 0')
            ->assertSee('Sakit 0')->assertSee('Alpa 0');
    }

    /**
     * Kelas yang belum punya siswa perlu kalimat sendiri. Tanpa itu, tabelnya
     * kosong dan partial matriks menampilkan "tidak ada data absensi", yang
     * mengarahkan orang menunggu absensi padahal yang belum ada adalah siswanya.
     */
    public function test_kelas_tanpa_siswa_menampilkan_pesan_yang_berbeda(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();
        Student::query()->delete();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Tidak ada siswa terdaftar di kelas ini.')
            ->assertDontSee('Tidak ada data absensi pada periode ini.');

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Kelas ada, tetapi belum ada siswa terdaftar.')
            ->assertDontSee('Tidak ada data absensi pada periode ini.');
    }
}
