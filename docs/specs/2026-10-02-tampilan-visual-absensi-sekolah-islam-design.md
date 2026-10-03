# Desain: Tampilan Visual Absensi Sekolah Islam

Tanggal: 2026-10-02
Status: disetujui dan sudah diimplementasikan
Cakupan: logo sekolah, token warna, aksen hijau, mode terang sebagai default, tombol pengalih tema

## 1. Tujuan dan batas

Memberi identitas visual yang bisa dikenali sebagai aplikasi sekolah Islam tanpa
mengubah struktur, alur, atau perilaku aplikasi yang sudah jadi.

Yang **tidak** termasuk pekerjaan ini:

- Menata ulang layout, sidebar, tabel, atau form
- Mengganti komponen Flux dengan komponen lain
- Menambah halaman, fitur, atau state baru
- Mengubah apa pun di `vendor/`

Cakupan disepakati bersama pengguna: ganti token warna dan aksen saja, dengan
satu penambahan di luar cakupan awal, yaitu tombol pengalih tema.

## 2. Dasar teknis yang sudah diverifikasi

Bagian ini hasil pembacaan kode, bukan asumsi. Semua sudah dicek ulang pada
commit `fae4224`.

- **Tailwind v4 dengan konfigurasi CSS-first.** Token warna ditulis di blok
  `@theme` pada `resources/css/app.css`. Tailwind v4 tidak memakai
  `tailwind.config.js` untuk token warna.
- **Flux sudah menyediakan sistem tema.** `@fluxAppearance` di
  `resources/views/partials/head.blade.php` menyisipkan
  `window.Flux.applyAppearance(appearance)` yang menerima `'light'`, `'dark'`,
  atau `'system'`, menyimpan pilihan ke `localStorage['flux.appearance']`, dan
  mengatur `color-scheme`. Fungsinya sudah terpakai di kedua layout yang aktif.
- **Aplikasi sudah mengikuti preferensi sistem, bukan selalu gelap.**
  `<html class="dark">` pada layout hanya fallback sebelum script berjalan;
  `applyAppearance` dengan fallback `'system'` menimpanya. Melihat tag itu saja
  tanpa membaca `@fluxAppearance` menghasilkan kesimpulan yang salah.
- **Hanya dua layout yang aktif.** `resources/views/layouts/app/sidebar.blade.php`
  dipakai 13 komponen Livewire lewat `#[Layout('layouts.app')]` dan satu view
  `recap.index`. `resources/views/layouts/auth/card.blade.php` dipakai oleh
  `Auth\Login`. `resources/views/layouts/app/header.blade.php` **tidak dipakai
  siapa pun**, itu sisa starter kit, dan tidak boleh disentuh dalam pekerjaan ini.
- **`--color-accent` menentukan banyak hal sekaligus.** Token itu dipakai Flux
  untuk tombol utama, item sidebar aktif (`sidebar.item` punya prop `accent`
  default `true`), latar logo, dan focus ring. Mengganti `--color-accent` akan
  mengubah hampir seluruh tampilan, bukan memberi aksen kecil.
- **Tidak ada test yang mengikat ke kelas warna.** Nol test menyentuh `accent`,
  `dark:`, atau `bg-zinc`. Redesign ini tidak akan memecahkan test yang ada, dan
  sekaligus berarti tidak ada test yang bisa mendeteksi kemundangan visual.
  Bagian 7 menambahkannya.
- **Halaman login memakai `@fluxAppearance` juga.** Karena `partials/head`
  mencakup kedua layout, perubahan default berlaku di login dan di aplikasi.

## 3. Keputusan warna

Dasar netral `zinc` dan seluruh palet abu-abu **tidak berubah**. Yang ditambahkan
satu kelompok token baru di blok `@theme`:

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

`--color-accent`, `--color-accent-content`, dan `--color-accent-foreground`
**tidak disentuh**. Konsekuensinya: tombol Simpan, Tambah, dan item menu aktif
tetap netral seperti sekarang, dan hanya tiga hal berikut yang memakai hijau.

### 3.1 Dua pemakai token

| Pemakai | File | Perubahan |
| --- | --- | --- |
| Angka Hadir | `livewire/dashboard.blade.php` | `text-green-700` jadi `text-madrasah-700` |
| Kartu statistik | `livewire/dashboard.blade.php` | tambah `border-s-4 border-s-madrasah-600` pada lima kartu |

Izin, Sakit, dan Alpa tetap memakai warna Tailwind bawaan. Hanya Hadir yang
mengikuti token sekolah, supaya hijau tidak kehilangan makna sebagai "lulus".

Dulu ada pemakai ketiga, yaitu latar logo. Baris itu dicabut setelah diukur:
logo sekolah mengisi seluruh kanvasnya sampai ke keempat tepi, sehingga kotak
32px di belakangnya tertutup 100% dan warna kotak apa pun tidak akan pernah
terlihat. Memilih warna untuk sesuatu yang tidak terlihat hanya menambah
keraguan tanpa efek.

### 3.2 Kenapa tidak mengganti `--color-accent`

Pilihan paling mudah adalah menulis `--color-accent: var(--color-madrasah-700)`.
itu kelihatan seperti satu baris, tapi efeknya mengganti warna hampir semua
tombol dan menu aktif di aplikasi, bukan aksen kecil. Kalau nanti identitasnya
masih terasa lemah, menaikkan intensitas berarti mengubah nilai
`--color-accent`. Tidak ada perubahan lain yang perlu dibatalkan, jadi jalur naik
tersedia tanpa membangun ulang apa pun.

## 4. Keputusan mode terang

Empat perubahan, semuanya di luar `vendor/`.

1. **Hapus `class="dark"`** dari `layouts/app/sidebar.blade.php` dan
   `layouts/auth/card.blade.php`. Selama masih ada, pengguna mode terang melihat
   kilat gelap sesaat sebelum script berjalan.

2. **Timpa default ke terang.** Tambah blok script kecil tepat setelah
   `@fluxAppearance` di `resources/views/partials/head.blade.php`:

   ```html
   <script>
       window.Flux.applyAppearance(window.localStorage.getItem('flux.appearance') || 'light')
   </script>
   ```

   Tanpa ini, "terang sebagai default" hanya berlaku bagi pengguna yang sistem
   operasinya terang. Baris `|| 'light'` inilah yang menjadikannya default untuk
   semua orang.

   Script ini inline, tidak dibungkus `DOMContentLoaded`. Kalau dibungkus, ia
   baru berjalan setelah dokumen selesai dimuat, dan kilat tema yang sedang
   dihindari justru muncul. `@fluxAppearance` sendiri sudah memuat panggilan
   dengan `'system'` sebagai fallback; panggilan kedua inilah yang menimpanya.

3. **Komponen pengalih.** `resources/views/components/theme-toggle.blade.php`.
   Persyaratannya sudah ditetapkan, bentuk kodenya ditulis saat implementasi:

   - elemen `<button>` native dengan `type="button"`, bukan `div` atau `a`
   - `aria-label` berbahasa Indonesia yang berubah mengikuti mode saat ini,
     "Aktifkan mode gelap" atau "Aktifkan mode terang", karena tombolnya hanya
     berisi ikon
   - ikon Flux `sun` atau `moon`
   - memanggil `Flux.applyAppearance('light'|'dark')` dan tidak menyimpan apa pun
     sendiri, karena `localStorage` milik Flux
   - menampilkan mode saat ini sebelum script `<head>` berjalan, supaya tombol
     tidak melompat setelah halaman selesai dimuat

4. **Pasang di dua tempat**: sidebar desktop dan header mobile, keduanya di
   `layouts/app/sidebar.blade.php`. Tombol desktop memakai `hidden lg:flex` di
   bagian bawah sidebar, tombol mobile memakai `lg:hidden` di header, sehingga
   tepat satu tombol terlihat pada lebar berapa pun. Tombol tidak ditaruh di
   header sidebar karena saat sidebar dikecilkan isinya hanya logo dan tombol
   collapse, tidak ada ruang. Tidak dipasang di halaman login karena login belum
   punya navigasi.

Dua keadaan saja, terang dan gelap. Opsi "Ikuti sistem" sengaja tidak disertakan
karena butuh satu keadaan ekstra di komponen dan satu nilai `'system'`; bisa
ditambahkan nanti tanpa mengubah struktur.

## 5. Yang tidak dikerjakan di sini

`resources/views/layouts/app/header.blade.php` adalah kode mati dari starter kit.
Menghapusnya menggoda karena menyisakan satu `class="dark"` yang bertentangan
dengan keputusan mode terang, tetapi itu refactor yang tidak terkait dengan
permintaan. Dicatat, tidak disentuh. Kalau dihapus nanti, buang `class="dark"`
miliknya di commit yang sama supaya tidak ada file bertema gelap yang tersisa di
repo.

## 6. Bukti ketelitian

Empat klaim di dokumen ini sempat salah sebelum diverifikasi, dan semuanya
menunjukkan bahaya yang sama, yaitu menyimpulkan dari satu file tanpa membaca
file di sekitarnya:

- "Aplikasi selalu gelap" salah. `@fluxAppearance` sudah menimpanya dengan
  preferensi sistem.
- "Header layout aktif" salah. Tidak ada pemanggilnya.
- "Aksen hijau hanya di beberapa tempat" harus dicek ulang, karena
  `--color-accent` ternyata menentukan tombol, menu aktif, logo, dan focus ring.
- "Logo sekolah diletakkan di kotak hijau" salah. Logo itu full-bleed, jadi
  kotaknya tertutup 100% dan tidak pernah terlihat.

Perubahan visual tidak punya gejalanya yang sama seperti perubahan fungsional.
Salah baca tidak muncul sebagai test merah, tapi sebagai tampilan yang terasa
aneh dan sulit ditelusuri sumbernya. Dua dari empat kesalahan di atas baru ketahuan
karena sebuah berkas diukur, bukan karena dibaca.

## 7. Test

Tidak ada test yang mengikat kelas warna, dan sengaja tidak ditambah. Kelas
warna adalah detail implementasi, mengujinya rapuh tanpa nilai. Yang ditambahkan
hanya perilaku yang bisa rusak diam-diam:

| Test | Isi |
| --- | --- |
| `AppearanceDefaultTest` | Halaman login dan dashboard memakai fallback `'light'`, script itu dijalankan setelah `@fluxAppearance`, dan tag `<html>` tidak lagi memuat `class="dark"` |
| `ThemeToggleTest` | Komponen merender `<button type="button">`, punya nama aksesibel yang berubah, dua ikon, meneruskan atribut luar, dan tidak menulis `localStorage` sendiri |
| `ThemeTogglePlacementTest` | Tombol muncul tepat dua kali di halaman aplikasi, dan tidak sama sekali di halaman login |

Test harus membayar ongkos yang sebanding dengan risikonya. Kalau test komponen
hanya mengecek `assertSee('sun')`, ia akan lulus walaupun tombolnya tidak punya
nama aksesibel, dan itu persis kegagalan yang paling mungkin terjadi pada tombol
ikon.

Dua perilaku tidak bisa diuji PHPUnit dan harus dicek di browser: tombolnya benar
berpindah tema saat diklik, dan tema ikut berubah di tab lain yang terbuka. Keduanya
memakai JavaScript; PHPUnit tidak menjalankan JavaScript.

## 8. Logo sekolah

**Sudah diputuskan.** `components/app-logo-icon.blade.php` tidak lagi memuat SVG
logo Laravel. Logo resmi sekolah dipasang sebagai gambar JPEG di
`public/images/logo-sekolah.jpeg` (364 x 356, sekitar 22 KB).

Berkas JPEG punya latar putih yang tidak bisa dihapus tanpa kehilangan channel
alpha, dan PNG dengan alpha pun tetap akan menampilkan kotak putih di dalam kotak
hijau. Karena itu kotak warna di belakang logo dihapus seluruhnya, bukan diganti
warna. Logo tampil apa adanya.

Keterbatasan yang diketahui: di sidebar mode gelap, yang berlatar `dark:bg-zinc-900`,
latar putih logo terlihat sebagai persegi putih berukuran 32px. Itu diterima,
karena logonya sendiri yang berwarna dan persegi putih itu terbaca sebagai kotak
logo, bukan sebagai kesalahan.

Kalau suatu saat sekolah menyediakan berkas PNG atau SVG dengan latar transparan,
cukup ganti satu file di `public/images/` dan keterbatasan ini hilang tanpa
perubahan kode lain.

## 9. Verifikasi

- `php artisan test` , 369 test harus tetap lulus
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse`
- `npm run build`
- Cek manual di browser pada mode terang dan gelap, 375px dan desktop

## 10. Catatan teknis

Tailwind v4 memindai seluruh proyek, bukan hanya `resources/views`. Folder `docs/`
tidak ada di `.gitignore`, sehingga nama-nama kelas yang hanya tertulis di dokumen
perancangan ikut terbaca dan membuat utility-nya ter-generate di CSS produksi.
Untuk plan ini dampaknya nol karena semua kelas yang dipakai view juga ada di
view. Tapi kalau suatu saat ada kelas yang sengaja hanya disebut di dokumen,
CSS lokal akan berbeda dari CSS di server. Perbaikannya ada di `@source` pada
`resources/css/app.css`, di luar cakupan plan ini.