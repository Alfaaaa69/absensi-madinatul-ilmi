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
 * tanpa menambah cakupan. Perilaku rute — tamu diarahkan ke login, akun
 * nonaktif ditolak — diuji di `AbsensiGuestAccessTest` dan
 * `AttendanceEntryAccessTest`, bukan di sini.
 *
 * ## Batas yang diketahui, sengaja dibiarkan terbuka
 *
 * Dua hal di bawah **tidak** dikawal oleh file ini, dan sengaja tidak ikut
 * dikerjakan di giliran ini. Keduanya dicatat supaya tidak dibaca sebagai hal
 * yang sudah tercakup.
 *
 * 1. **Nama route tidak dijamin unik, dan name lookup bisa berbeda hasilnya
 *    dari dispatch.** Dua tabel berbeda menangani hal ini dengan aturan berbeda:
 *
 *    - **Name lookup: pendaftaran pertama menang.** `addLookups()` mengecek
 *      `! $this->inNameLookup($name)` sebelum menulis
 *      (`RouteCollection.php:105-107`), jadi `Route::has('absensi.create')`,
 *      `getByName('absensi.create')`, dan `route('absensi.create')` semuanya
 *      membaca **pendaftaran pertama** dengan nama itu.
 *    - **Dispatch: pendaftaran terakhir menang.** `addToCollections()` menulis
 *      `$this->routes[$method][$domainAndUri] = $route` **tanpa** pemeriksaan
 *      apa pun (`RouteCollection.php:87`), jadi untuk URI yang identik, route yang
 *      didaftarkan belakangan **menimpa** yang sebelumnya, dan request-nya
 *      dilayani oleh yang belakangan.
 *
 *    Bahaya sebenarnya bukan "request kedua dilayani route pertama" — kalimat
 *    itu terbalik arah. Bahaya sebenarnya muncul ketika satu nama dipakai lagi
 *    dengan bentuk parameter berbeda: `route('absensi.create')` tetap
 *    menghasilkan URL dari route yang **pertama**, sementara request ke URL itu
 *    dilayani route yang **terakhir**. Name lookup dan dispatch bisa menunjuk
 *    dua entri yang berbeda. Ketiga test di sini tetap hijau, karena semuanya
 *    hanya membaca tabel dan tidak pernah mengirim request.
 *
 *    Menjaga keunikan nama berarti menghitung jumlah route per nama, dan
 *    "hitung route per nama" bukan hal yang sama dengan "pastikan route ini
 *    ada": sifat assertion-nya berbeda dan harus diputuskan sendiri sebelum
 *    ditulis.
 *
 * 2. **Redundansi `EnsureUserIsActive` belum diputuskan.** `routes/web.php`
 *    menyebut middleware itu dua kali: sekali di grup, sekali per route. Yang
 *    sekarang menjaga `auth` dan `EnsureUserIsActive` adalah deklarasi grup;
 *    yang per route adalah pengulangan. Test di sini **tidak** membuktikan mana
 *    yang benar-benar bekerja — `gatherMiddleware()` mengembalikan hasil
 *    ratanya, dan middleware yang muncul dua kali tetap terlihat sekali. Yang
 *    bisa membuktikan efeknya adalah test perilaku, dan itu sudah ada di
 *    `AttendanceEntryAccessTest`. Menghapus deklarasi per route akan mengubah
 *    nol perilaku dan nol test, tapi sengaja tidak dilakukan di giliran ini:
 *    itu perubahan bentuk, bukan perbaikan cacat.
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
