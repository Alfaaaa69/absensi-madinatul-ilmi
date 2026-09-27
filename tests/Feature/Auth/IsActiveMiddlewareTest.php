<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IsActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('login')) {
            Route::get('/login', fn () => 'ok')->name('login');
        }

        // 'web' wajib: di aplikasi nyata routes/web.php otomatis dapat grup ini,
        // dan StartSession adalah syarat supaya $request->session() bisa dipakai
        // middleware saat invalidate().
        Route::middleware(['web', 'auth', EnsureUserIsActive::class])
            ->get('/halaman-diuji', fn () => 'ok')
            ->name('halaman.diuji');

        $this->refreshRouteNameLookups();
    }

    /**
     * Route::name() hanya mengisi action['as']; RouteCollection baru menyusun
     * nameList saat refreshNameLookups(), yang di aplikasi normal dipanggil
     * RouteServiceProvider saat boot. Route yang didaftarkan setelah boot —
     * seperti route double di test ini — tidak akan terlihat oleh route('login')
     * tanpa refresh manual.
     */
    protected function refreshRouteNameLookups(): void
    {
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_akun_aktif_boleh_melewati_middleware(): void
    {
        $user = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($user)->get('/halaman-diuji')->assertOk();
    }

    public function test_akun_nonaktif_diarahkan_kembali_ke_login_dengan_pesan(): void
    {
        $user = User::factory()->guru()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get('/halaman-diuji')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('nuptk');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_kehilangan_sessionnya(): void
    {
        $user = User::factory()->guru()->create(['is_active' => false]);

        $this->actingAs($user)->get('/halaman-diuji');

        $this->assertGuest();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/halaman-diuji')->assertRedirect('/login');
    }
}
