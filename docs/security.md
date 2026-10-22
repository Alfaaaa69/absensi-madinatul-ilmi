# Security Checklist — Sistem Absensi Sekolah

Dokumen ini mencatat aturan keamanan yang wajib diterapkan di sistem, sebagai referensi untuk development (manual maupun lewat AI agent).

## 1. Privasi & visibilitas
- Semua halaman wajib login — tidak ada halaman data yang bisa diakses tanpa autentikasi
- Registrasi publik dimatikan; akun guru hanya dibuat oleh admin
- Login pakai email + password (bukan NUPTK/NIP — sesuai keputusan terakhir)
- `robots.txt` di-set `Disallow: /` agar tidak diindeks mesin pencari
- Tambahkan meta tag `<meta name="robots" content="noindex, nofollow">` atau header `X-Robots-Tag: noindex, nofollow` di seluruh halaman

## 2. Autentikasi
- Gunakan Laravel Breeze/Fortify bawaan, jangan bikin sistem auth sendiri dari nol
- Aktifkan rate limiting di route login (middleware `throttle` bawaan Laravel)
- Password minimal 8 karakter, kombinasi huruf besar/kecil dan angka
- Wajib ganti password default saat login pertama kali
- Pasang HTTPS (SSL) di hosting — wajib, bukan opsional

## 3. Otorisasi & scoping akses
- Semua pembatasan akses (guru hanya lihat jadwalnya sendiri, wali kelas hanya kelasnya) diterapkan di level query lewat Policy/Gate — tidak boleh hanya disembunyikan di UI
- Test manual: login sebagai guru/wali kelas, coba akses data milik user lain lewat manipulasi ID di URL — harus mendapat 403, bukan ter-load

## 4. Proteksi data & environment
- File `.env` tidak boleh ikut ke-push ke Git (cek `.gitignore`)
- `APP_DEBUG=false` di environment production — mencegah stack trace & path server bocor ke publik
- Backup database dilakukan berkala (otomatis dari hosting, atau manual `mysqldump`)

## 5. Proteksi input
- Gunakan Eloquent/query builder, hindari raw SQL manual (proteksi SQL injection)
- Gunakan `{{ }}` di Blade untuk output (auto-escape), hindari `{!! !!}` kecuali benar-benar diperlukan (celah XSS)
- Validasi semua input form lewat `rules()` di Livewire component (NISN, nama, dll)

## 6. Operasional
- Update dependency (`composer update`) secara berkala, cek breaking changes sebelum deploy
- Audit trail/activity log (opsional, nice-to-have): siapa login kapan, perubahan data penting — bisa pakai package seperti `spatie/laravel-activitylog`, juga berguna sebagai bukti di laporan PKM

## Prioritas
Untuk skala PKM, fokus utama ada di bagian 1-4 — itu yang paling sering jadi celah di project sekolah/akademik. Bagian 5-6 tetap penting tapi bisa menyusul kalau waktu terbatas.
