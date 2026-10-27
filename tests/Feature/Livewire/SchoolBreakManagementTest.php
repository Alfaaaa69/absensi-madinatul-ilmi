<?php

namespace Tests\Feature\Livewire;

use App\Enums\DayOfWeek;
use App\Livewire\SchoolBreakManagement;
use App\Models\SchoolBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolBreakManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_jeda_hari_lain(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->set('day_of_week', (string) DayOfWeek::Rabu->value)
            ->set('label', 'Istirahat')
            ->set('start_time', '09:30')
            ->set('end_time', '09:45')
            ->set('after', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_breaks', ['day_of_week' => 3, 'label' => 'Istirahat']);
    }

    public function test_admin_bisa_mengubah_dan_menghapus_jeda(): void
    {
        $admin = User::factory()->admin()->create();
        $jeda = SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'label' => 'Lama',
            'start_time' => '09:00',
            'end_time' => '09:15',
        ]);

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->call('editForm', $jeda->id)
            ->assertSet('label', 'Lama')
            ->set('label', 'Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_breaks', ['id' => $jeda->id, 'label' => 'Baru']);

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->call('hapus', $jeda->id);

        $this->assertDatabaseMissing('school_breaks', ['id' => $jeda->id]);
    }

    public function test_jam_mulai_harus_sebelum_jam_selesai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->set('day_of_week', '2')
            ->set('label', 'Istirahat')
            ->set('start_time', '10:00')
            ->set('end_time', '09:00')
            ->set('after', 4)
            ->call('save')
            ->assertHasErrors(['end_time']);
    }

    public function test_hapus_jeda_yang_sedang_diedit_mengosongkan_form(): void
    {
        $admin = User::factory()->admin()->create();
        $jeda = SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '09:00',
            'end_time' => '09:15',
        ]);

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->call('editForm', $jeda->id)
            ->call('hapus', $jeda->id)
            ->assertSet('editingId', null)
            ->assertSet('label', '');
    }

    public function test_guru_tidak_bisa_membuka_halaman_jeda(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get('/master/jeda')->assertForbidden();
    }
}
