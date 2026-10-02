<?php

namespace Tests\Feature\Livewire;

use App\Enums\UserRole;
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
            ->assertFileDownloaded('rekap-absensi-2025-09-01-2025-09-15.xlsx');
    }

    public function test_export_ditolak_saat_periode_tidak_valid(): void
    {
        RecapScenario::waliKelas();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-07')
            ->call('exportExcel')
            ->assertStatus(422);
    }

    /**
     * `mount()` hanya jalan sekali, pada request yang merender halaman. Tab yang
     * sudah terbuka tidak memanggilnya lagi, jadi header tab yang fechar sudah
     * memuat `Gate::authorize('viewAnyRecap')` tidak cukup untuk action yang
     *Tilepak di request berikutnya.
     *
     * Skenario nyatanya: admin diturunkan jadi guru dari panel user management
     * sementara tab lapse-nya masih terbuka. Tanpa pemeriksaan ulang, request
     * export berikutnya tetap berjalan memakai header yang sudah tersimpan.
     */
    public function test_export_ditolak_setelah_admin_turun_jadi_guru(): void
    {
        RecapScenario::waliKelas();
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(AdminAttendanceReport::class);

        $admin->forceFill(['role' => UserRole::Guru])->save();
        // Permintaan berikutnya membaca ulang user dari database.
        auth()->setUser($admin->fresh());

        $component->call('exportExcel')->assertForbidden();
    }

    public function test_export_pdf_ditolak_setelah_admin_turun_jadi_guru(): void
    {
        RecapScenario::waliKelas();
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(AdminAttendanceReport::class);

        $admin->forceFill(['role' => UserRole::Guru])->save();
        auth()->setUser($admin->fresh());

        $component->call('exportPdf')->assertForbidden();
    }
}
