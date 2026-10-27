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
        $html = $this->render();

        $this->assertStringNotContainsString(
            'setItem',
            $html,
            'Penyimpanan tema milik Flux, komponen hanya boleh membaca localStorage, tidak menulis'
        );

        $this->assertStringNotContainsString(
            'removeItem',
            $html,
            'Penyimpanan tema milik Flux, komponen tidak boleh menghapus localStorage'
        );
    }

    /**
     * Flux mendefinisikan window.Flux.applyAppearance lewat @fluxAppearance, tapi
     * begitu Alpine boot Flux mengganti window.Flux dengan store reaktif yang isinya
     * hanya toast/modal/modals/appearance/dark. applyAppearance sudah tidak ada, jadi
     * pemanggilan dari tombol melempar TypeError dan mode gelap tidak pernah berubah.
     */
    public function test_tidak_memanggil_apply_appearance_yang_sudah_dihapus_flux(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString(
            'window.Flux.applyAppearance',
            $html,
            'window.Flux sudah bukan objek applyAppearance setelah Alpine boot'
        );
    }

    public function test_toggle_mengubah_state_reaktif_flux(): void
    {
        $html = $this->render();

        $this->assertStringContainsString(
            '$flux.dark = ! $flux.dark',
            $html,
            'Tombol harus mengubah state reaktif Flux, bukan memanggil fungsi yang sudah hilang'
        );

        $this->assertStringContainsString(
            '$flux.dark ?',
            $html,
            'Label aksesibel harus membaca state yang sama dengan tombol'
        );
    }

    public function test_handler_storage_menerapkan_tema_yang_dibagikan(): void
    {
        $html = $this->render();

        $this->assertStringContainsString(
            "addEventListener('storage'",
            $html,
            'Tombol harus mendengarkan perubahan localStorage dari tab lain'
        );

        $this->assertMatchesRegularExpression(
            '/const onStorage[\s\S]*?\$flux\.appearance\s*=/',
            $html,
            'Saat tab lain mengganti tema, tab ini harus benar-benar menerapkan tema baru. '
            .'Hanya membaca ulang class <html> milik tab sendiri tidak mengubah apa pun'
        );

        $this->assertStringContainsString(
            "removeEventListener('storage'",
            $html,
            'Listener harus dilepas saat komponen dibongkar, kalau tidak setiap navigasi '
            .'wire:navigate meninggalkan listener mati di window'
        );
    }

    public function test_meneruskan_atribut_luar(): void
    {
        $html = Blade::render('<x-theme-toggle class="mt-2" />');

        $this->assertStringContainsString('mt-2', $html);
    }
}
