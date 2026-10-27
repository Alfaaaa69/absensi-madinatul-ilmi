# SOP Deploy Sistem Absensi

Dokumen ini untuk admin hosting sekolah, bukan untuk developer. Setiap perintah
yang disebut di sini harus dijalankan dari Terminal cPanel, bukan dari File
Manager.

## Prasyarat

- Akses cPanel, phpMyAdmin, dan Terminal (bergantung pada paket hosting; sebagian
  shared hosting tidak menyediakan Terminal).
- MySQL 8.
- PHP 8.3 atau lebih baru, dengan ekstensi berikut aktif: `zip`, `gd`, `pdo_mysql`,
  `mbstring`, `openssl`, `fileinfo`, `dom`. `zip` wajib karena export Excel memakai
  PhpSpreadsheet; tanpa itu export akan gagal saat dipakai, bukan saat deploy.
- Target versi PHP harus sama dengan `composer.json` (PHP `^8.3`).

## Sebelum mulai: backup

Backup database lebih dulu, setiap kali akan mengubah apa pun di server. Lihat
bagian "Backup rutin" untuk caranya.

## Deploy pertama

1. Unggah kode ke `public_html/absensi`. Folder `public` Laravel menjadi document
   root: di cPanel, document root diarahkan ke `public_html/absensi/public`.
2. Buat database dan user di cPanel, lalu isi `.env`:

   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://domain-sekolah.sch.id
   APP_TIMEZONE=Asia/Jakarta
   QUEUE_CONNECTION=sync
   REKAP_FILE_PREFIX=rekap-absensi
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=nama_database
   DB_USERNAME=nama_user
   DB_PASSWORD=kata_sandi
   ```

   `APP_DEBUG=false` itu wajib di produksi. Dengan `true`, setiap exception
   memunculkan isi `.env` termasuk kata sandi database di layar pengguna.

3. `php artisan key:generate` **hanya kalau `APP_KEY` belum pernah dibuat**.
   Mengganti `APP_KEY` yang sudah dipakai membuat seluruh cookie sesi lama tidak
   berlaku dan semua pengguna harus login ulang. Kalau sudah ada `APP_KEY`,
   pakai ulang yang itu.

4. Pasang dependency. Folder `vendor` tidak ikut masuk ke dalam upload - tanpa
   langkah ini tidak ada satu pun perintah `php artisan` yang bisa berjalan:

   ```
   composer install --no-dev --optimize-autoloader
   ```

   Kalau cPanel tidak menyediakan Composer di SSH, jalankan dari komputer lokal
   dengan `composer install --no-dev --optimize-autoloader` lalu unggah folder
   `vendor` hasil itu. Jangan pernah mengunggah `vendor` yang berisi
   development dependency ke produksi.

5. `php artisan migrate --force`

6. **Jangan menjalankan `php artisan db:seed --force` di produksi.**
   Seeder yang ada di repository adalah `DemoSchoolSeeder`: 1 admin, 10 guru,
   7 kelas, 210 siswa, 8 mapel, 105 jadwal, dan 3.150 baris absensi contoh -
   semuanya dengan kata sandi `password`. Dijalankan di produksi, itu mengisi
   database sekolah dengan data fiktif yang harus dihapus manual.

   Buat akun admin pertama tanpa seeder, lewat Tinker:

   ```
   php artisan tinker
   ```

   Lalu di dalam Tinker:

   ```php
   App\Models\User::create([
       'name' => 'Nama Kepala Sekolah',
       'nuptk' => '123456789012345678',
       'password' => 'kata-sandi-yang-kuat',
       'role' => App\Enums\UserRole::Admin,
       'is_active' => true,
   ]);
   ```

    NUPTK wajib diisi dan unik. Akun ini yang dipakai untuk membuat akun guru
    dan data master lainnya lewat menu User.

7. `php artisan storage:link` - **hanya kalau aplikasi memakai disk `public`.**
   Saat ini rekap Excel dan PDF dikirim langsung sebagai respons unduhan dan
   tidak disimpan ke disk, jadi langkah ini belum dibutuhkan. Jangan menjalankannya
   sekarang, dan jangan ikut mengunggah folder `vendor` ke server.

   Kalau nanti ada fitur yang menyimpan berkas ke disk `public` (foto siswa,
   berkas impor, hasil export yang ditahan di server), symlink sering diblokir di
   shared hosting. Handling-nya: jalankan `php artisan storage:unlink`, salin isi
   `storage/app/public` ke `public/storage` lewat File Manager, dan pastikan
   `public/storage` writable. Cara ini tidak punya symlink, jadi setelah
   `deploy` berikutnya yang mengunggah kode baru, salinan itu hilang dan harus
   diulang.

8. Buka `/up`. Halaman ini harus mengembalikan 200 sebelum halaman lama diganti.

9. Login sebagai admin, buat tahun ajaran pertama, lalu isi kelas, mapel, siswa,
   jadwal, dan akun guru. Data master ini tidak punya seeder produksi, jadi
   seluruhnya diinput lewat aplikasi.

## Deploy berikutnya

1. Backup database lewat phpMyAdmin: export semua tabel sebagai `.sql`.
2. Unggah kode baru. Pertahankan file `.env` yang sudah ada di server - jangan
   ikut menimpa dengan `.env.example`.
3. `php artisan migrate --force`
4. `php artisan optimize:clear` - wajib karena cache konfigurasi, route, dan
   view masih berisi versi lama kalau tidak.
5. Buka `/up`, coba login, dan cocokkan satu rekap bulanan dengan hasil export
   minggu sebelumnya. Angka rekap harus sama persis; kalau berbeda, jangan
   diteruskan ke pengguna.

## Backup rutin

- Database di-export mingguan lewat phpMyAdmin, disimpan **minimal 4 minggu**,
  dan **di luar server**. Backup yang hanya ada di server yang sama hilang
  bersama server saat hosting bermasalah atau akun hosting dihapus.
- Simpan satu backup per bulan selama satu tahun. Rekap bulanan sering dipakai
  untuk laporan PKM, dan rekonstruksi angka lama jauh lebih sulit daripada
  menyimpan berkasnya.
- Kode tidak perlu dicadangkan karena sumbernya ada di repository. Yang tidak
  ada di repository: `.env` dan `APP_KEY`. Keduanya wajib dicatat aman, kalau
  tidak, database hasil backup tidak bisa dipakai lagi.

## Pemulihan

1. Buat database baru di cPanel.
2. Import file `.sql` hasil backup ke database itu lewat phpMyAdmin.
3. Salin `.env` lama ke folder kode baru, termasuk `APP_KEY` yang sama. Kalau
   `APP_KEY` berbeda, cookie sesi lama tidak berlaku - tidak merusak data,
   tapi semua orang harus login ulang tanpa perlu.
4. Jalankan `composer install --no-dev --optimize-autoloader`, lalu
   `php artisan migrate:status` untuk memastikan tidak ada migrasi yang
   tertinggal, dan `php artisan migrate --force` kalau memang ada.
5. `php artisan storage:link` **hanya kalau disk `public` sudah benar-benar
   dipakai** (lihat langkah 7 pada Deploy pertama). Kalau symlink diblokir,
   ikuti cara salin manual di langkah tersebut.
6. Arahkan document root ke folder `public` yang baru, lalu buka `/up`.
7. Login sebagai admin dan cocokkan satu rekap bulanan dengan hasil export
   minggu sebelumnya. Ini satu-satunya pemeriksaan yang benar-benar memastikan
   pemulihan berhasil: migrate berhasil bukan berarti data utuh.

## Yang tidak boleh dilakukan

- **`php artisan migrate:fresh` di produksi.** Perintah itu menghapus seluruh
  tabel lalu membangun ulang dari nol: seluruh riwayat absensi hilang permanen.
  `DB_DATABASE` yang salah ketik di `.env` akan membuatnya salah sasaran, jadi
  periksa nilainya sebelum menjalankan perintah apa pun yang menghapus.
- **`php artisan migrate:reset`.** Sama seperti di atas - menghapus isi seluruh
  tabel.
- **`php artisan db:seed --force` di produksi.** Mengisi database dengan data
  demo, bukan data sekolah.
- **Mengganti `APP_KEY` tanpa alasan.** Semua sesi keluar dan cookie lama tidak
  berlaku. Kalau `APP_KEY` hilang, tidak ada cara memulihkannya dari kode:
  seluruh data terenkripsi yang bergantung padanya tidak bisa dibaca lagi.
- **Menjalankan `queue:work` atau scheduler.** `QUEUE_CONNECTION=sync` karena
  shared hosting tidak punya queue worker, dan aplikasi ini tidak punya scheduled
  task. `schedule:run` akan gagal atau diam-diam tidak berjalan.
- **Menghapus `storage/framework` tanpa menjalankan ulang optimize.** Hapus
  isinya (bukan foldernya) kalau disk mulai penuh, lalu `php artisan
  optimize:clear`.

## Kalau ada yang salah setelah deploy

| Gejala | Yang perlu dicek |
| --- | --- |
| Halaman 500 | `storage/logs/laravel.log` |
| `/up` tidak terbuka | Versi PHP, ekstensi aktif, dan `./storage` writable |
| Login selalu gagal | `APP_KEY` berubah, atau `users.is_active` false |
| Zona waktu meleset | `APP_TIMEZONE` di `.env`, lalu `php artisan optimize:clear` |
| Export Excel gagal | ekstensi `zip` belum aktif |
| File unduhan tidak tersimpan | `php artisan storage:unlink`, lalu salin manual seperti langkah 6 |
