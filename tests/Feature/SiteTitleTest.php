<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_judul_situs_memakai_nama_sekolah(): void
    {
        config(['app.name' => 'Sistem Absensi Sekolah']);

        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<title>\s*(.*?)\s*<\/title>/is', $html, $matches);
        $this->assertNotEmpty($matches, 'tag <title> tidak ditemukan');
        $this->assertStringContainsString('Sistem Absensi Sekolah', $matches[1]);
    }

    public function test_judul_halaman_menggabungkan_nama_halaman_dengan_nama_sekolah(): void
    {
        config(['app.name' => 'Sistem Absensi Sekolah']);

        $user = User::factory()->guru()->create(['is_active' => true]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<title>\s*(.*?)\s*<\/title>/is', $html, $matches);
        $this->assertNotEmpty($matches, 'tag <title> tidak ditemukan');
        $this->assertStringNotContainsStringIgnoringCase(
            'laravel',
            $matches[1],
            'Judul situs jatuh ke branding Laravel'
        );
    }

    /**
     * config/app.php Provides 'name' => env('APP_NAME', 'Laravel'). Karena kunci
     * config itu selalu ada, fallback di dalam view seperti
     * config('app.name', 'Laravel') tidak pernah dipakai. Jadi kalau APP_NAME
     * tidak ada di .env, nama Laravel muncul dari file config, bukan dari view.
     * Test ini memeriksa sumbernya secara langsung.
     */
    public function test_default_nama_aplikasi_di_config_bukan_laravel(): void
    {
        $config = file_get_contents(config_path('app.php'));

        $this->assertStringNotContainsString(
            "env('APP_NAME', 'Laravel')",
            $config,
            "Default nama aplikasi di config/app.php masih 'Laravel'. "
            .'Kalau APP_NAME tidak ada di .env, nama itu yang tampil di tab browser'
        );
    }

    public function test_default_nama_aplikasi_menghasilkan_nama_sekolah(): void
    {
        $config = file_get_contents(config_path('app.php'));

        $this->assertMatchesRegularExpression(
            "/'name'\s*=>\s*env\('APP_NAME',\s*'Sistem Absensi Sekolah'\)/",
            $config,
            'Default nama aplikasi di config/app.php harus nama sekolah'
        );
    }
}
