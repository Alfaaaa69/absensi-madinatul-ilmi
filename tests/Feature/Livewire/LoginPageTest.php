<?php

namespace Tests\Feature\Livewire;

use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('dashboard')) {
            Route::middleware(['web', 'auth', EnsureUserIsActive::class])
                ->get('/dashboard', fn () => 'ok')
                ->name('dashboard');

            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_halaman_login_tampil_untuk_tamu(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('NUPTK');
    }

    public function test_component_menampilkan_form(): void
    {
        Livewire::test(Login::class)
            ->assertOk()
            ->assertSee('NUPTK')
            ->assertSee('Password');
    }

    public function test_validasi_nuptk_dan_password_wajib_diisi(): void
    {
        Livewire::test(Login::class)
            ->set('nuptk', '')
            ->set('password', '')
            ->call('authenticate')
            ->assertHasErrors(['nuptk' => 'required', 'password' => 'required']);
    }

    public function test_nuptk_harus_18_digit_angka(): void
    {
        Livewire::test(Login::class)
            ->set('nuptk', '12345')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasErrors(['nuptk' => 'digits']);
    }

    public function test_password_kurang_dari_delapan_karakter_ditolak(): void
    {
        Livewire::test(Login::class)
            ->set('nuptk', '100000000000000001')
            ->set('password', 'pendek')
            ->call('authenticate')
            ->assertHasErrors(['password' => 'min']);
    }

    public function test_login_berhasil_mengarah_ke_dashboard(): void
    {
        $user = User::factory()->guru()->create([
            'nuptk' => '100000000000000001',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('nuptk', '100000000000000001')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_gagal_login_menampilkan_pesan_pada_field_nuptk(): void
    {
        Livewire::test(Login::class)
            ->set('nuptk', '999999999999999999')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasErrors('nuptk');

        $this->assertGuest();
    }

    public function test_password_tidak_pernah_ditampilkan_kembali_setelah_gagal(): void
    {
        Livewire::test(Login::class)
            ->set('nuptk', '999999999999999999')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertSet('password', '');
    }

    public function test_tidak_ada_link_registrasi_atau_lupa_password(): void
    {
        Livewire::test(Login::class)
            ->assertDontSee('Daftar')
            ->assertDontSee('Lupa password')
            ->assertDontSee('Daftarkan')
            ->assertDontSee('href="/register"', false);
    }

    public function test_halaman_login_menolak_user_yang_sudah_login(): void
    {
        $user = User::factory()->guru()->create();

        $this->actingAs($user)->get('/login')->assertRedirect();
    }
}
