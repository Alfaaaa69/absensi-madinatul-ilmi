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
            ->assertSee('Email');
    }

    public function test_component_menampilkan_form(): void
    {
        Livewire::test(Login::class)
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('Password');
    }

    public function test_validasi_email_dan_password_wajib_diisi(): void
    {
        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', '')
            ->call('authenticate')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_email_harus_format_email(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'bukan-email')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasErrors(['email' => 'email']);
    }

    public function test_password_kurang_dari_delapan_karakter_ditolak(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'guru@sekolah.sch.id')
            ->set('password', 'pendek')
            ->call('authenticate')
            ->assertHasErrors(['password' => 'min']);
    }

    public function test_login_berhasil_mengarah_ke_dashboard(): void
    {
        $user = User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'password' => 'rahasia-kuat-123',
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'guru@sekolah.sch.id')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_gagal_login_menampilkan_pesan_pada_field_email(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'tidak-ada@sekolah.sch.id')
            ->set('password', 'rahasia-kuat-123')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_tidak_pernah_ditampilkan_kembali_setelah_gagal(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'tidak-ada@sekolah.sch.id')
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
