# Plan 6: Tampilan Visual — Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah identitas visual aplikasi menjadi netral dengan aksen hijau sekolah, menjadikan mode terang sebagai default, dan menambahkan tombol pengalih tema.

**Architecture:** Empat bagian terpisah. Token warna masuk ke blok `@theme` Tailwind v4 yang sudah ada. Mode terang diubah di satu file yang dipakai kedua layout, yaitu `partials/head.blade.php`, ditambah menghapus `class="dark"` dari dua layout. Tombol tema dibuat sebagai komponen Blade tanpa state sendiri; penyimpanan tetap milik Flux. Logo sekolah dipasang sebagai gambar di `public/images/`.

**Tech Stack:** Laravel 13, Livewire 4, Flux 2.13, Tailwind CSS v4.1 (CSS-first), Alpine (ikut Flux), PHPUnit 12.

**Spec:** `docs/specs/2026-10-02-tampilan-visual-absensi-sekolah-islam-design.md`

## Global Constraints

- Token warna hanya lewat blok `@theme` di `resources/css/app.css`. Tidak ada `tailwind.config.js` di proyek ini.
- `--color-accent`, `--color-accent-content`, dan `--color-accent-foreground` **tidak boleh diubah** di mana pun.
- Tidak ada perubahan di `vendor/`.
- `resources/views/layouts/app/header.blade.php` adalah kode mati, **tidak boleh disentuh**.
- Semua teks antarmuka berbahasa Indonesia dan tidak disimpan dengan fungsi `__()` yang bermasalah; pakai pola `__('Kalimat')` yang sudah dipakai repo.
- PHP >= 8.3. Gaya kode mengikuti repo: 4 spasi, tanpa docblock tambahan, tanpa komentar kecuali diminta.
- Deploy ke shared hosting cPanel: `QUEUE_CONNECTION=sync`, tidak ada scheduled task.
- Tidak boleh menambah dependensi composer atau npm.

## Review Focus

Lima kondisi yang paling mungkin menggigit pengguna, masing-masing dipintest di task pemiliknya:

1. **Nilai `localStorage` yang tidak dikenal** (mis. `"darkk"` atau string rusak) — `Flux.applyAppearance()` tidak punya cabang untuk itu, jadi tidak ada yang berubah dan tidak ada error. Pengguna harus tetap melihat mode terang. Dipintest di Task 5 sebagai manual check, karena ini perilaku JavaScript yang tidak bisa dievaluasi PHPUnit.
2. **Tag `<html>` masih memuat `class="dark"`** — pengguna dengan sistem operasi terang melihat kilat gelap sebelum script berjalan. Dipintest di Task 4.
3. **Script default terang ditulis sebelum `@fluxAppearance`** — `window.Flux` belum ada, `ReferenceError`, tema tidak pernah terpasang. Dipintest di Task 4 lewat pemeriksaan urutan.
4. **Tombol tema hilang di halaman login** — pengguna tidak bisa pindah tema sebelum masuk. Ini keputusan spec yang disengaja, jadi harus dipintest agar tidak hilang tak sengaja. Dipintest di Task 6.
5. **Logo berlatar putih di sidebar gelap** — JPEG tidak punya alpha, jadi muncul persegi putih 32px di atas `dark:bg-zinc-900`. Dipintest di Task 6 sebagai pemeriksaan manual di browser.

---

### Task 1: Logo sekolah

Perubahan sudah dikerjakan di working tree dan perlu diverifikasi lalu di-commit sebagai satu unit.

**Files:**
- Create: `public/images/logo-sekolah.jpeg` (sudah ada, 364x356, `mime: image/jpeg`)
- Modify: `resources/views/components/app-logo-icon.blade.php`
- Modify: `resources/views/components/app-logo.blade.php`
- Modify: `resources/views/layouts/auth/card.blade.php`

**Interfaces:**
- Produces: aset `asset('images/logo-sekolah.jpeg')` yang dipanggil komponen `<x-app-logo-icon>`. Task berikutnya tidak menyentuh aset ini.

- [ ] **Step 1: Pastikan isi komponen benar**

Isi `resources/views/components/app-logo-icon.blade.php` harus persis:

```blade
<img src="{{ asset('images/logo-sekolah.jpeg') }}"
    alt="{{ config('app.name', 'Absensi Sekolah') }}"
    {{ $attributes->merge(['class' => 'size-8 rounded-md object-contain']) }} />
```

- [ ] **Step 2: Pastikan kotak warna di belakang logo sudah dihapus**

`resources/views/components/app-logo.blade.php` tidak boleh lagi memuat `bg-accent-content` pada slot logo, dan `<x-app-logo-icon>` dipanggil dengan `class="size-8"` pada kedua cabang (`@if($sidebar)` dan `@else`).

Alasannya: logo full-bleed, area opaque-nya menutupi seluruh kanvas, sehingga kotak di belakangnya tidak akan pernah terlihat.

- [ ] **Step 3: Pastikan class sisa SVG di halaman login dibersihkan**

`resources/views/layouts/auth/card.blade.php` memanggil `<x-app-logo-icon class="size-9" />`. Kelas `fill-current`, `text-black`, dan `dark:text-white` harus hilang, karena kelas itu hanya berefek pada elemen SVG.

- [ ] **Step 4: Jalankan test**

Run: `php artisan test`
Expected: 358 test lulus, 980 assertion.

- [ ] **Step 5: Commit**

```bash
git add public/images resources/views/components/app-logo-icon.blade.php resources/views/components/app-logo.blade.php resources/views/layouts/auth/card.blade.php
git commit -m "feat: pasang logo sekolah di sidebar dan halaman login"
```

---

### Task 2: Token warna madrasah

**Files:**
- Modify: `resources/css/app.css:11-29` (blok `@theme`)
- Test: tidak ada test PHPUnit. Lihat catatan di bawah.

**Interfaces:**
- Produces: kelas utilitas Tailwind `text-madrasah-700`, `border-s-madrasah-600`, dan seterusnya, yang dipakai Task 3.

> Catatan TDD untuk task ini: token CSS tidak punya perilaku yang bisa dipanggil dari PHPUnit. Menguji isi `app.css` dengan membaca teks file adalah tes rapuh yang menguji implementasi, bukan hasil. Karena itu task ini diverifikasi lewat hasil build, bukan lewat test PHP. Ini kecuali yang disengaja dan disepakati di spec bagian 7.

- [ ] **Step 1: Tambahkan sepuluh token ke blok `@theme`**

Tambahkan di dalam `@theme { ... }`, setelah blok `--color-zinc-950` dan sebelum `--color-accent`:

```css
    --color-madrasah-50: #ecfdf5;
    --color-madrasah-100: #d1fae5;
    --color-madrasah-200: #a7f3d0;
    --color-madrasah-300: #6ee7b7;
    --color-madrasah-400: #34d399;
    --color-madrasah-500: #10b981;
    --color-madrasah-600: #059669;
    --color-madrasah-700: #047857;
    --color-madrasah-800: #065f46;
    --color-madrasah-900: #064e3b;
```

- [ ] **Step 2: Pastikan blok accent tidak tersentuh**

Baris `--color-accent`, `--color-accent-content`, `--color-accent-foreground` harus tetap bernilai persis seperti sekarang. Mengubahnya akan mengganti warna tombol utama dan item menu aktif, yang di luar cakupan.

- [ ] **Step 3: Build CSS**

Run: `npm run build`
Expected: selesai tanpa error.

Kalau `npm run build` gagal karena `public/hot` ada (server dev sedang berjalan), hentikan server dev lebih dulu lalu build ulang.

- [ ] **Step 4: Pastikan token benar-benar terkompilasi**

Run: `rg -- '--color-madrasah-700:#047857' public/build/assets/`

Expected: satu baris ditemukan di `public/build/assets/app-*.css`. Kalau tidak ditemukan, nama token salah atau blok `@theme` tidak terbaca Tailwind.

- [ ] **Step 5: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: tambah token warna madrasah"
```

`public/build` sudah masuk `.gitignore`, jadi tidak ikut di-commit.

---

### Task 3: Aksen hijau di dashboard

**Files:**
- Modify: `resources/views/livewire/dashboard.blade.php:18-41` (lima `flux:card` pertama)
- Test: tidak ada test PHPUnit. Lihat catatan di bawah.

**Interfaces:**
- Consumes: token `madrasah-700` dan `madrasah-600` dari Task 2.

> Catatan TDD untuk task ini: sama seperti Task 2, ini murni kelas tampilan. Spec bagian 7 sudah menetapkan bahwa kelas warna sengaja tidak dipintest. Verifikasi lewat build dan pemeriksaan visual.

- [ ] **Step 1: Ubah warna angka Hadir**

Di `resources/views/livewire/dashboard.blade.php`, baris pembuka kartu Hadir:

```blade
        <flux:card class="p-4">
            <flux:heading size="sm" class="text-zinc-600">Hadir</flux:heading>
            <p class="text-2xl font-semibold text-madrasah-700">{{ $counts['hadir'] }}</p>
        </flux:card>
```

Hanya `text-green-700` yang berubah menjadi `text-madrasah-700`. Kelas `text-blue-700` pada Izin, `text-yellow-700` pada Sakit, dan `text-red-700` pada Alpa **tetap** — hijau dicadangkan untuk makna "lulus" dan warna lain membedakan status.

- [ ] **Step 2: Tambahkan garis hijau pada kelima kartu**

Setiap `flux:card` di dalam `div.grid` pada baris 17 mendapat kelas tambahan `border-s-4 border-s-madrasah-600` di samping `p-4`. Termasuk kartu Kelas Diawasi, Hadir, Izin, Sakit, dan Alpa.

Gunakan `border-s-4` plus `border-s-madrasah-600`, bukan `border-madrasah-600`. Yang terakhir akan mengganti warna keempat sisi dan menimpa border abu-abu bawaan `flux:card`.

- [ ] **Step 3: Pastikan tidak ada kartu keenam yang ikut berubah**

Baris setelah lima kartu tersebut tidak boleh diberi `border-s-*`. Kalau ada kartu lain di view yang sama, biarkan.

- [ ] **Step 4: Build dan pastikan utilitas logis terpasang**

Run: `npm run build`
Expected: selesai tanpa error.

Run: `rg -- 'border-inline-start' public/build/assets/app-*.css`
Expected: minimal satu deklarasi `border-inline-start` ditemukan. Kalau tidak, `border-s-4` tidak ter-generate.

- [ ] **Step 5: Jalankan test dashboard**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/dashboard.blade.php
git commit -m "feat: aksen hijau pada kartu statistik dashboard"
```

---

### Task 4: Mode terang sebagai default

**Files:**
- Create: `tests/Feature/AppearanceDefaultTest.php`
- Modify: `resources/views/partials/head.blade.php:15`
- Modify: `resources/views/layouts/app/sidebar.blade.php:2`
- Modify: `resources/views/layouts/auth/card.blade.php:2`

**Interfaces:**
- Consumes: `window.Flux.applyAppearance(appearance)` yang sudah tersedia secara global dari directive `@fluxAppearance`. Task ini tidak mendefinisikan API baru.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/AppearanceDefaultTest.php`:

```php
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

        $this->assertMatchesRegularExpression('/<html\b[^>]*>/i', $html);

        preg_match('/<html\b[^>]*>/i', $html, $matches);
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
        $this->assertStringNotContainsString('dark', $matches[0]);
    }
}
```

Ganti kalimat berkarakter asing pada pesan kegagalan di test ketiga dengan kalimat Indonesia biasa sebelum menyimpan.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=AppearanceDefaultTest`
Expected: FAIL pada test pertama dan ketiga. Test kedua juga gagal karena string `'light'` belum ada di halaman.

- [ ] **Step 3: Tambahkan script default terang**

Di `resources/views/partials/head.blade.php`, tambahkan tepat setelah baris `@fluxAppearance`:

```html
<script>
    window.Flux.applyAppearance(window.localStorage.getItem('flux.appearance') || 'light')
</script>
```

Script ini-inline dan tidak dibungkus `DOMContentLoaded`, supaya berjalan sebelum `<body>` dirender dan kilat gelap tidak sempat terlihat.

Perhatikan: `@fluxAppearance` sendiri sudah memuat panggilan dengan `'system'` sebagai fallback. Panggilan kedua inilah yang menimpanya menjadi `'light'`. Keduanya boleh ada.

- [ ] **Step 4: Hapus `class="dark"` dari dua layout**

Di `resources/views/layouts/app/sidebar.blade.php:2`:

```html
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

Perubahan yang sama di `resources/views/layouts/auth/card.blade.php:2`.

Jangan menyentuh layout `header.blade.php` yang tidak terpakai.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=AppearanceDefaultTest`
Expected: 4 test lulus.

- [ ] **Step 6: Jalankan test penuh**

Run: `php artisan test`
Expected: 362 test lulus.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/AppearanceDefaultTest.php resources/views/partials/head.blade.php resources/views/layouts/app/sidebar.blade.php resources/views/layouts/auth/card.blade.php
git commit -m "feat: jadikan mode terang sebagai default aplikasi"
```

---

### Task 5: Komponen pengalih tema

**Files:**
- Create: `resources/views/components/theme-toggle.blade.php`
- Create: `tests/Feature/ThemeToggleTest.php`

**Interfaces:**
- Consumes: `window.Flux.applyAppearance('light'|'dark')` dan `window.addEventListener('storage', ...)` dari browser.
- Produces: komponen `<x-theme-toggle />` yang menerima dan melempar atribut HTML biasa, misalnya `class`.

> Catatan TDD untuk task ini: `Blade::render()` mengevaluasi komponen di sisi server, jadi test bisa mengikat kontrak markup. Perilaku klik-nya adalah JavaScript dan tidak bisa dievaluasi PHPUnit — itu masuk daftar manual check di Step 7.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/ThemeToggleTest.php`:

```php
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

        $this->assertStringContainsString('<flux:icon.sun', $html);
        $this->assertStringContainsString('<flux:icon.moon', $html);
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
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ThemeToggleTest`
Expected: FAIL dengan pesan "Unable to locate a class or view for component [theme-toggle]".

- [ ] **Step 3: Buat komponennya**

Buat `resources/views/components/theme-toggle.blade.php`:

```blade
<button
    type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark
            window.Flux.applyAppearance(this.dark ? 'dark' : 'light')
        },
        sync() {
            this.dark = document.documentElement.classList.contains('dark')
        },
    }"
    x-init="window.addEventListener('storage', sync)"
    x-on:click="toggle()"
    x-bind:aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
    {{ $attributes->merge([
        'class' => 'flex items-center justify-center rounded-md p-2 text-zinc-500 transition hover:bg-zinc-200/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white',
    ]) }}
>
    <flux:icon.sun class="size-5 dark:hidden" />
    <flux:icon.moon class="hidden size-5 dark:block" />
</button>
```

`x-data` membaca `classList` pada `<html>`, yang sudah final karena script di Task 4 berjalan di `<head>`. Pemakaian ikon lewat `dark:hidden` dan `dark:block` membuat mode benar sejak render pertama tanpa perlu `x-show` atau `x-cloak`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ThemeToggleTest`
Expected: 4 test lulus.

Kalau test ikon gagal, periksa nama stub ikon memang ada di `vendor/livewire/flux/stubs/resources/views/flux/icon/sun.blade.php` dan `moon.blade.php`.

- [ ] **Step 5: Pastikan komponen meneruskan atribut dari luar**

Jalankan `php artisan test --filter=ThemeToggleTest` lagi setelah menambah sementara pemanggilan `Blade::render('<x-theme-toggle class="mt-2" />')` di dalam test, dan pastikan hasil render memuat `mt-2`. Setelah yakin, hapus baris sementara itu.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/theme-toggle.blade.php tests/Feature/ThemeToggleTest.php
git commit -m "feat: komponen pengalih tema terang dan gelap"
```

- [ ] **Step 7: Manual check di browser**

Jalankan `php artisan serve` plus `npm run dev`, lalu buka aplikasi dan periksa:

- Klik tombol: ikon berganti antara matahari dan bulan, latar berubah antara terang dan gelap.
- Muat ulang halaman: pilihan tema bertahan, jadi `localStorage` benar-benar ditulis oleh Flux.
- Buka dua tab, ganti tema di satu tab, lalu tab lain ikut berubah tanpa perlu dimuat ulang. Ini yang menguji `storage` listener di `x-init`.
- Setel `localStorage['flux.appearance']` menjadi `"darkk"` lewat konsol browser, muat ulang. Halaman harus tetap tampil normal tanpa error JavaScript, karena `applyAppearance` tidak punya cabang untuk nilai itu.

Kalau salah satu gagal, perbaiki komponen dan ulangi test di Step 4.

---

### Task 6: Pemasangan toggle di dua layout

**Files:**
- Create: `tests/Feature/ThemeTogglePlacementTest.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php:8-11` (sidebar header) dan `:38-72` (mobile header)

**Interfaces:**
- Consumes: komponen `<x-theme-toggle />` dari Task 5.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/ThemeTogglePlacementTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ThemeTogglePlacementTest`
Expected: test pertama FAIL dengan nilai 0, test kedua PASS karena halaman login memang belum memuatnya.

- [ ] **Step 3: Pasang di sidebar desktop**

Di `resources/views/layouts/app/sidebar.blade.php`, di dalam `<flux:sidebar.header>`, sisipkan setelah `<flux:sidebar.collapse class="lg:hidden" />`:

```blade
                <x-theme-toggle class="ml-auto" />
```

`ml-auto` mendorong tombol ke kanan, di sebelah tombol collapse.

- [ ] **Step 4: Pasang di header mobile**

Di berkas yang sama, di dalam `<flux:header class="lg:hidden">`, sisipkan sebelum `<flux:dropdown ...>`:

```blade
                <x-theme-toggle />
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ThemeTogglePlacementTest`
Expected: 2 test lulus.

- [ ] **Step 6: Jalankan test penuh**

Run: `php artisan test`
Expected: 368 test lulus.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/ThemeTogglePlacementTest.php resources/views/layouts/app/sidebar.blade.php
git commit -m "feat: pasang pengalih tema di sidebar dan header mobile"
```

- [ ] **Step 8: Manual check di browser**

Periksa pada 375px dan 1440px, dalam mode terang dan gelap:

- Tombol terlihat dan bisa diklik di kedua ukuran.
- Di sidebar desktop yang sedang dikecilkan, tombol tidak bertumpuk dengan logo atau keluar dari kotak header.
- Logo JPEG tetap proporsional, tidak gepeng, karena `object-contain` bekerja.
- Di mode gelap, perhatikan persegi putih di belakang logo Acceptable, karena JPEG tidak punya kanal alpha. Catat sebagai keterbatasan yang diketahui, bukan bug.

---

### Task 7: Koreksi spec dan verifikasi akhir

**Files:**
- Modify: `docs/specs/2026-10-02-tampilan-visual-absensi-sekolah-islam-design.md`

**Interfaces:**
- Consumes: hasil Task 1 sampai Task 6.

- [ ] **Step 1: Koreksi bagian 3.1 di spec**

Tabel di spec bagian 3.1 masih menyebut baris "Latar logo | `components/app-logo.blade.php` | `bg-accent-content` jadi `bg-madrasah-700`". Baris itu **tidak berlaku lagi**, karena logonya full-bleed sehingga kotak di belakangnya tidak pernah terlihat.

Ganti baris tersebut dengan:

```
| Logo sekolah | `components/app-logo-icon.blade.php` | Ganti SVG Laravel dengan `<img>` ke `public/images/logo-sekolah.jpeg` |
```

Ubah kalimat pengantar tabel menjadi menyebut hanya dua pemakai token.

- [ ] **Step 2: Perbarui bagian 9 di spec**

Bagian 9 membahas logo sebagai keputusan terbuka. Tambahkan satu paragraf penutup yang menyatakan keputusan sudah diambil: logo sekolah dipakai, format JPEG, diletakkan di `public/images/`, dan tidak ada motif yang digambar ulang.

- [ ] **Step 3: Pastikan spec tidak lagi menjanjikan kotak hijau pada logo**

Cari seluruh kemunculan `bg-madrasah-700` di spec. Hanya boleh muncul kalau terkait angka Hadir atau kartu statistik. Kalau masih ada yang menyebut kotak logo, hapus.

- [ ] **Step 4: Scan karakter asing di spec dan kode yang diubah**

Jalankan ini di root repo. Perintah ini mencari rentang karakter CJK, Cyrillic, dan kana, yaitu kelas karakter yang menyebabkan teks Indonesia rusak saat ditulis:

```powershell
$files = @(
  'docs/specs/2026-10-02-tampilan-visual-absensi-sekolah-islam-design.md',
  'docs/specs/plans/2026-10-02-plan-6-tampilan-visual.md',
  'resources/views/components/theme-toggle.blade.php',
  'resources/views/components/app-logo-icon.blade.php',
  'resources/views/partials/head.blade.php',
  'resources/views/livewire/dashboard.blade.php',
  'tests/Feature/AppearanceDefaultTest.php',
  'tests/Feature/ThemeToggleTest.php',
  'tests/Feature/ThemeTogglePlacementTest.php'
)
$rx = '[\u1100-\u11FF\u2E80-\u9FFF\uAC00-\uD7AF\uF900-\uFAFF\uFE30-\uFE4F\uFF00-\uFFEF\u0400-\u04FF]'
$bad = $false
foreach ($f in $files) {
  $hit = Select-String -Path $f -Pattern $rx
  if ($hit) { $bad = $true; $hit | ForEach-Object { "$f`:$($_.LineNumber): $($_.Line.Trim())" } }
}
if ($bad) { Write-Output 'GAGAL: karakter asing ditemukan' } else { Write-Output 'BERSIH' }
```

Expected: `BERSIH`.

- [ ] **Step 5: Jalankan Pint**

Run: `vendor/bin/pint --test`
Expected: tidak ada berkas yang perlu diperbaiki.

- [ ] **Step 6: Jalankan PHPStan**

Run: `vendor/bin/phpstan analyse`
Expected: 0 error.

- [ ] **Step 7: Build CSS terakhir**

Run: `npm run build`
Expected: selesai tanpa error.

- [ ] **Step 8: Jalankan test penuh**

Run: `php artisan test`
Expected: 368 test lulus, 0 gagal.

- [ ] **Step 9: Commit**

```bash
git add docs/specs/2026-10-02-tampilan-visual-absensi-sekolah-islam-design.md
git commit -m "docs: koreksi spec tampilan visual sesuai implementasi logo"
```

---

## Verifikasi Akhir

Setelah semua task selesai, sebelum menyatakan selesai:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
npm run build
git status --short
git log --oneline -7
```

`git status --short` harus kosong kecuali ada perubahan yang belum di-commit.

Bukti manual yang tidak bisa digantikan test: buka aplikasi di 375px dan 1440px, ganti tema berulang kali, muat ulang, dan pastikan tidak ada kilat tema. Catat hasilnya, jangan hanya diasumsikan berhasil.