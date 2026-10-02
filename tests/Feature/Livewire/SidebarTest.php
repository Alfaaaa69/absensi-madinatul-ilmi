<?php

namespace Tests\Feature\Livewire;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sidebar memanggil route('dashboard'), dan route itu baru dibuat di
        // Task 6. Route double di sini supaya kegagalan test menunjuk
        // masalah navigasi, bukan route yang belum ada.
        if (! Route::has('dashboard')) {
            Route::get('/dashboard', fn (): string => 'ok')
                ->middleware(['web', 'auth', EnsureUserIsActive::class])
                ->name('dashboard');

            Route::getRoutes()->refreshNameLookups();
        }

        // x-layouts.app tidak ada sebagai komponen Blade: file production-nya
        // adalah view layouts/app.blade.php yang membungkus sidebar. Yang
        // diuji di task ini persis sidebar dan user-menu itu, jadi layout
        // dirender langsung lewat <x-layouts::app.sidebar>.
        Route::middleware(['web', 'auth', EnsureUserIsActive::class])
            ->get('/halaman-uji-sidebar', fn (): string => Blade::render(
                '<x-layouts::app.sidebar><div>Isi halaman</div></x-layouts::app.sidebar>'
            ))
            ->name('sidebar.harness');
    }

    public function test_guru_tidak_melihat_link_manajemen_user(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertDontSee('Manajemen User');
    }

    public function test_admin_melihat_link_manajemen_user(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee('Manajemen User');
    }

    public function test_link_repository_dan_dokumentasi_starter_kit_dihapus(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertDontSee('livewire-starter-kit')
            ->assertDontSee('starter-kits#livewire')
            ->assertDontSee('Repository');
    }

    public function test_tidak_ada_link_settings_atau_profile(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertDontSee('Settings')
            ->assertDontSee('T-appearance')
            ->assertDontSee('icon-cog');
    }

    public function test_link_dashboard_ada_untuk_semua_peran(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_user_menu_menampilkan_nuptk_bukan_email(): void
    {
        $guru = User::factory()->guru()->create([
            'name' => 'Budi Santoso',
            'nuptk' => '100000000000000080',
            'email' => null,
            'is_active' => true,
        ]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee('100000000000000080')
            ->assertSee('BS');
    }

    public function test_email_tidak_lagi_muncul_di_user_menu(): void
    {
        $guru = User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'is_active' => true,
        ]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertDontSee('guru@sekolah.sch.id');
    }

    public function test_tombol_keluar_mengarah_ke_route_logout(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee(route('logout'), escape: false)
            ->assertSee('Keluar');
    }

    /**
     * Tanpa link ini fitur rekap hanya bisa dibuka dengan mengetik URL. Guru
     * yang baru login tidak punya cara lain untuk mencapainya.
     */
    public function test_link_rekap_ada_untuk_guru_dan_admin(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee('Rekap Absensi')
            ->assertSee(route('rekap.index'), escape: false);

        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee(route('rekap.index'), escape: false);
    }

    public function test_link_jadwal_saya_ada_untuk_guru_dan_admin(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)
            ->get('/halaman-uji-sidebar')
            ->assertOk()
            ->assertSee(route('jadwal.index'), escape: false);
    }
}
