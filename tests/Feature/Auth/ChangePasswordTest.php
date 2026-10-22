<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function userHarusGantiPassword(): User
    {
        return User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'password' => 'Password-awal-123',
            'must_change_password' => true,
            'is_active' => true,
        ]);
    }

    public function test_halaman_data_dialihkan_ke_ganti_password(): void
    {
        $user = $this->userHarusGantiPassword();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));
    }

    public function test_halaman_ganti_password_tetap_bisa_dibuka(): void
    {
        $this->actingAs($this->userHarusGantiPassword())
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('Ganti Kata Sandi');
    }

    public function test_user_tanpa_flag_tidak_dialihkan(): void
    {
        $user = User::factory()->guru()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_password_berhasil_diganti_dan_flag_dicabut(): void
    {
        $user = $this->userHarusGantiPassword();

        Livewire::actingAs($user)
            ->test(ChangePassword::class)
            ->set('current_password', 'Password-awal-123')
            ->set('password', 'Password-baru-456')
            ->set('password_confirmation', 'Password-baru-456')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Password-baru-456', $user->password));
    }

    public function test_password_saat_ini_salah_ditolak(): void
    {
        $user = $this->userHarusGantiPassword();

        Livewire::actingAs($user)
            ->test(ChangePassword::class)
            ->set('current_password', 'Password-salah-123')
            ->set('password', 'Password-baru-456')
            ->set('password_confirmation', 'Password-baru-456')
            ->call('save')
            ->assertHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_password_baru_tanpa_huruf_besar_ditolak(): void
    {
        $user = $this->userHarusGantiPassword();

        Livewire::actingAs($user)
            ->test(ChangePassword::class)
            ->set('current_password', 'Password-awal-123')
            ->set('password', 'password-baru-456')
            ->set('password_confirmation', 'password-baru-456')
            ->call('save')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_setelah_ganti_password_dashboard_bisa_dibuka(): void
    {
        $user = $this->userHarusGantiPassword();

        Livewire::actingAs($user)
            ->test(ChangePassword::class)
            ->set('current_password', 'Password-awal-123')
            ->set('password', 'Password-baru-456')
            ->set('password_confirmation', 'Password-baru-456')
            ->call('save');

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk();
    }
}
