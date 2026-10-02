<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminAttendanceReport;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class AdminAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_seluruh_kelas(): void
    {
        $s = RecapScenario::waliKelas();
        $lain = RecapScenario::kelasLain($s, 'IX-B', User::factory()->guru()->create());
        RecapScenario::siswaDi($lain, 'Siti Rahma');

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('VII-A')->assertSee('Ahmad Fauzi')
            ->assertSee('IX-B')->assertSee('Siti Rahma');
    }

    public function test_guru_ditolak_membuka_laporan_admin(): void
    {
        $s = RecapScenario::waliKelas();

        $this->actingAs($s->wali)->get(route('rekap.admin'))->assertForbidden();
    }

    public function test_tahun_ajaran_kosong_menampilkan_pesan(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.');
    }

    public function test_tidak_ada_absensi_menampilkan_pesan(): void
    {
        RecapScenario::waliKelas();
        Attendance::query()->delete();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');
    }

    public function test_export_excel_mengunduh_file(): void
    {
        RecapScenario::waliKelas();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-09')
            ->call('exportExcel')
            ->assertFileDownloaded('rekap-absensi-2025-09-01-2025-09-30.xlsx');
    }

    public function test_export_ditolak_saat_periode_tidak_valid(): void
    {
        RecapScenario::waliKelas();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-07')
            ->call('exportExcel')
            ->assertStatus(422);
    }
}
