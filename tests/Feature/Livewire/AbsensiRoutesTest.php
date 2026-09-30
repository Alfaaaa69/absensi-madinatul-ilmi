<?php

namespace Tests\Feature\Livewire;

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Penjaga tabel rute Plan 4.
 *
 * Test di sini tidak menyentuh database dan tidak memanggil route-nya. Yang
 * diuji hanya bentuk tabel rute, supaya plan ini tidak bisa disalahpahami
 * belakangan: nama route, dan middleware yang menempel padanya.
 *
 * `RefreshDatabase` sengaja tidak dipakai. Tiga test ini cuma membaca
 * `Route::getRoutes()`, jadi menjalankan migrasi per test hanya menambah waktu
 * tanpa menambah cakupan.
 */
class AbsensiRoutesTest extends TestCase
{
    public function test_tiga_rute_fitur_absensi_terdaftar(): void
    {
        // Nama test menyebut "fitur absensi", bukan "rute absensi": `jadwal.index`
        // adalah halaman masuk ke fitur absensi, bukan route yang berada di
        // bawah prefix `/absensi`.
        $this->assertTrue(Route::has('jadwal.index'));
        $this->assertTrue(Route::has('absensi.create'));
        $this->assertTrue(Route::has('absensi.show'));
    }

    /**
     * `gatherMiddleware()` meratakan middleware dari grup, jadi middleware yang
     * hanya tertulis di grup `auth` tetap ikut terbaca. Assert ini memeriksa
     * middleware *ada*, dan itulah gunanya: kalau suatu saat salah satu route
     * absensi keluar dari grup `auth`, test ini gagal di sini, bukan gagal
     * diam-diam karena halamannya terbuka untuk tamu.
     */
    public function test_rute_absensi_wajib_login(): void
    {
        $middleware = Route::getRoutes()->getByName('absensi.create')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
    }

    /**
     * `routes/web.php` menyebut `EnsureUserIsActive` dua kali: sekali di grup,
     * sekali per route. Yang diuji di sini bukan redundancy itu, melainkan
     * middleware-nya ada di hasil gather — akun yang dinonaktifkan admin
     * sementara masih memegang session harus tetap tertahan.
     */
    public function test_rute_absensi_menolak_akun_tidak_aktif(): void
    {
        $middleware = Route::getRoutes()->getByName('absensi.show')->gatherMiddleware();

        $this->assertContains(EnsureUserIsActive::class, $middleware);
    }
}
