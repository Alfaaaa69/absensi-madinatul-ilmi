<?php

namespace Tests\Feature\Livewire;

use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga perilaku untuk tamu yang menyentuh halaman absensi.
 *
 * Kenapa kelas ini terpisah dari `AbsensiRoutesTest`: test di sana sengaja
 * tidak memakai `RefreshDatabase` dan tidak memanggil route-nya, hanya membaca
 * bentuk tabel rute. Yang diuji di sini justru route-nya **dipesan**, dan itu
 * butuh database — `absensi.create` dan `absensi.show` me-resolve `Schedule`
 * lewat route-model binding, yang berjalan di dalam pipeline `web` **sebelum**
 * middleware `auth` dieksekusi. Kalau tabel `schedules` kosong, yang error
 * adalah `ModelNotFoundException`, bukan redirect.
 *
 * File ini tetap di `Livewire/` supaya berada di sebelah `AbsensiRoutesTest`:
 * keduanya menjaga gerbang masuk yang sama, dan memisahkannya hanya karena satu
 * perlu database adalah memisahkan yang tidak perlu dipisahkan.
 *
 * Kesenjangan yang ditutup di sini: sebelumnya akses tamu hanya "terjaga" oleh
 * assertion string di `AbsensiRoutesTest` yang memeriksa `gatherMiddleware()`
 * berisi `auth`. Assertion itu membaca deklarasi middleware yang tersimpan di
 * objek rute — ia tidak pernah menjalankan middleware-nya. Rute yang disalin
 * keluar dari grup `auth` tapi tetap memanggil `->middleware('auth')` akan lolos,
 * dan satu-satunya bukti yang benar-benar menjalankan penjaganya adalah
 * request HTTP sungguhan dari session kosong.
 *
 * Ketiga rute di bawah diberi test masing-masing dengan sengaja: penjaga rute
 * bisa hilang di satu rute saja tanpa RoutesTest ikut memerah.
 */
class AbsensiGuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login_dari_daftar_jadwal(): void
    {
        $this->get(route('jadwal.index'))->assertRedirect(route('login'));
    }

    public function test_tamu_diarahkan_ke_login_dari_halaman_absensi_tertua(): void
    {
        $jadwal = Schedule::factory()->create();

        $this->get(route('absensi.create', $jadwal))->assertRedirect(route('login'));
    }

    public function test_tamu_diarahkan_ke_login_dari_halaman_tanggal_tertentu(): void
    {
        $jadwal = Schedule::factory()->create();

        $this->get(route('absensi.show', [$jadwal, today()->toDateString()]))
            ->assertRedirect(route('login'));
    }
}
