<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_memakai_default_terang(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(
                "window.Flux.applyAppearance(window.localStorage.getItem('flux.appearance') || 'light')",
                false
            );
    }

    public function test_script_default_terang_dijalankan_setelah_flux_appearance(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $fluxPosition = strpos($html, "applyAppearance(window.localStorage.getItem('flux.appearance') || 'system')");
        $lightPosition = strpos($html, "applyAppearance(window.localStorage.getItem('flux.appearance') || 'light')");

        $this->assertNotFalse($fluxPosition, 'script @fluxAppearance tidak ditemukan');
        $this->assertNotFalse($lightPosition, 'script default terang tidak ditemukan');
        $this->assertGreaterThan(
            $fluxPosition,
            $lightPosition,
            'script default terang harus dijalankan setelah @fluxAppearance, kalau tidak window.Flux belum ada'
        );
    }

    public function test_tag_html_pada_layout_aplikasi_tidak_mengharde_code_kelas_dark(): void
    {
        $user = User::factory()->guru()->create(['is_active' => true]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<html\b[^>]*>/i', $html, $matches);
        $this->assertNotEmpty($matches, 'tag <html> tidak ditemukan di halaman aplikasi');
        $this->assertStringNotContainsString(
            'dark',
            $matches[0],
            'Tag <html> masih memuat class="dark", pengguna dengan sistem terang akan melihat kilat gelap'
        );
    }

    public function test_tag_html_pada_layout_login_tidak_mengharde_code_kelas_dark(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<html\b[^>]*>/i', $html, $matches);
        $this->assertNotEmpty($matches, 'tag <html> tidak ditemukan di halaman login');
        $this->assertStringNotContainsString(
            'dark',
            $matches[0],
            'Tag <html> masih memuat class="dark", pengguna dengan sistem terang akan melihat kilat gelap'
        );
    }
}
