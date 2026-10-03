<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    private function render(): string
    {
        return Blade::render('<x-theme-toggle />');
    }

    public function test_render_elemen_button_native_dengan_tipe_button(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
    }

    public function test_nama_aksesibel_berubah_mengikuti_mode(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('x-bind:aria-label', $html);
        $this->assertStringContainsString('Aktifkan mode gelap', $html);
        $this->assertStringContainsString('Aktifkan mode terang', $html);
    }

    public function test_menampilkan_ikon_sun_dan_moon(): void
    {
        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'data-flux-icon'), 'Komponen harus merender tepat dua ikon');
        $this->assertStringContainsString('M12 3v2.25m6.364.386', $html, 'Ikon matahari tidak ditemukan');
        $this->assertStringContainsString('M21.752 15.002A9.72', $html, 'Ikon bulan tidak ditemukan');
        $this->assertStringContainsString('dark:hidden', $html);
        $this->assertStringContainsString('dark:block', $html);
    }

    public function test_tidak_menyimpan_state_sendiri_di_local_storage(): void
    {
        $this->assertStringNotContainsString(
            'localStorage',
            $this->render(),
            'Penyimpanan tema milik Flux, komponen tidak boleh menulis localStorage sendiri'
        );
    }

    public function test_meneruskan_atribut_luar(): void
    {
        $html = Blade::render('<x-theme-toggle class="mt-2" />');

        $this->assertStringContainsString('mt-2', $html);
    }
}
