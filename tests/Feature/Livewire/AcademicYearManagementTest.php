<?php

namespace Tests\Feature\Livewire;

use App\Enums\AcademicPeriod;
use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(string $nama, string $mulai, string $selesai, bool $aktif = false): array
    {
        return [
            'name' => $nama,
            'period' => AcademicPeriod::Ganjil->value,
            'start_date' => $mulai,
            'end_date' => $selesai,
            'is_active' => $aktif,
        ];
    }

    public function test_admin_bisa_menambah_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-07-01', '2025-12-31'))
            ->call('save')
            ->assertHasNoErrors();

        $tahun = AcademicYear::sole();

        $this->assertSame('2025/2026', $tahun->name);
        $this->assertSame(AcademicPeriod::Ganjil, $tahun->period);
        $this->assertSame('2025-07-01', $tahun->start_date->toDateString());
        $this->assertSame('2025-12-31', $tahun->end_date->toDateString());
        $this->assertFalse($tahun->is_active);
    }

    public function test_validasi_wajib_benar(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('', '', ''))
            ->call('save')
            ->assertHasErrors(['name', 'start_date', 'end_date']);

        $this->assertSame(0, AcademicYear::count());
    }

    public function test_tanggal_selesai_harus_setelah_tanggal_mulai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-12-31', '2025-07-01'))
            ->call('save')
            ->assertHasErrors(['end_date']);

        $this->assertSame(0, AcademicYear::count());
    }

    public function test_rentang_tanggal_bertindih_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        AcademicYear::factory()->create([
            'name' => '2025/2026 Ganjil',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026 Genap', '2025-10-01', '2026-06-30'))
            ->call('save')
            ->assertHasErrors(['start_date']);

        $this->assertSame(1, AcademicYear::count());
    }

    public function test_mengaktifkan_satu_tahun_menonaktifkan_yang_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $lama = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-07-01', '2026-06-30', true))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
        $this->assertFalse($lama->fresh()->is_active);
    }

    public function test_admin_bisa_mengubah_dan_menonaktifkan_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create([
            'name' => '2025/2026 Ganjil',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->call('editForm', $tahun->id)
            ->set('name', '2025/2026 Ganjil Revisi')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $disimpan = $tahun->fresh();

        $this->assertSame('2025/2026 Ganjil Revisi', $disimpan->name);
        $this->assertFalse($disimpan->is_active);
    }

    public function test_guru_tidak_bisa_membuka_halaman_tahun_ajaran(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/tahun-ajaran')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/tahun-ajaran')
            ->assertOk()
            ->assertSee('Tahun Ajaran');
    }
}
