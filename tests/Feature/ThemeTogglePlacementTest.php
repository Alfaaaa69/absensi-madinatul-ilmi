<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTogglePlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_terpasang_di_sidebar_desktop_dan_header_mobile(): void
    {
        $user = User::factory()->guru()->create(['is_active' => true]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(
            2,
            substr_count($html, 'Aktifkan mode gelap'),
            'Tombol tema harus muncul tepat dua kali: sidebar desktop dan header mobile'
        );
    }

    public function test_halaman_login_tidak_memuat_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Aktifkan mode gelap', false);
    }
}
