# CLAUDE.md — Sistem Absensi Sekolah (PKM)

Context project untuk AI coding agent. Baca file ini, lalu `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`, lalu `docs/PRD-Sistem-Absensi-Sekolah.md` sebelum mengerjakan task apapun.

Rencana implementasi yang sudah dipecah per fase ada di `docs/specs/plans/`. Eksekusi plan itu dengan skill `superpowers:executing-plans` atau `superpowers:subagent-driven-development` — jangan mulai mengoding dari spec langsung.

## Tentang project
Sistem absensi sekolah berbasis web untuk kegiatan PKM (Pengabdian kepada Masyarakat), dipakai oleh satu sekolah mitra. Detail lengkap requirement, scope, dan user flow ada di `docs/PRD-Sistem-Absensi-Sekolah.md` — jangan asumsikan fitur di luar yang tertulis di situ tanpa konfirmasi.

Skema database, role & scoping, dan batasan platform ditentukan di `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`. **Spec itu yang menang** kalau ada conflicts dengan PRD atau ERD.

## Status project
Aplikasi sudah diimplementasikan. Migrasi, model, Livewire component, policy, service, dan test sudah ada di repo. Branch pengembangan terbaru: `feat/plan-5-jadwal`.

Fitur utama yang sudah jalan: login email, manajemen user/kelas/mapel/tahun ajaran, jadwal, absensi, rekap wali kelas/guru/admin, export Excel/PDF, dan kewajiban ganti password untuk akun baru/reset dari admin.

Data demo diisi lewat `php artisan migrate --seed`. Seeder jadwal Senin ada di `SchoolTimetableSeeder`, `MondayScheduleSeeder`, dan `MondayScheduleCSeeder`.

## Tech stack
- Laravel 13 + Livewire 4 untuk seluruh UI — tidak pakai REST API terpisah/SPA
- Starter kit: **Livewire starter kit resmi Laravel** branch `dev-main` (bukan Jetstream, bukan Breeze). Tag `v1.0.0`/`v1.0.1` masih Laravel 12 dan tidak boleh dipakai
- PHP >= 8.3 (`laravel/livewire-starter-kit:dev-main` mensyaratkan `^8.3`)
- Database: MySQL, primary key UUID di semua tabel
- Laravel Excel untuk export Excel; **library terpisah** untuk export PDF (Laravel Excel tidak bisa PDF)
- Tailwind CSS untuk styling
- Testing: **PHPUnit 12** (ikut bawaan starter kit), **bukan Pest**. Filter test pakai `php artisan test --filter=NamaTest`

## Autentikasi
- Login memakai **email** (`users.email`), bukan NUPTK. NUPTK tetap dipakai sebagai identitas guru/siswa
- `users.email` nullable dan unique; akun tanpa email tidak bisa login, jadi admin wajib mengisi email saat membuat akun
- **Registrasi dimatikan** — `routes/register.php` dihapus, hanya admin yang membuat akun
- **Reset password via admin** dari menu user, bukan via email. Form "lupa password" bawaan dihapus
- Password dari admin (akun baru atau hasil reset) bersifat sementara: `users.must_change_password = true` memaksa pengguna membuka `/ubah-password` sebelum mengakses halaman data
- `users.is_active = false` → login ditolak dengan pesan jelas, bukan error 403
- Aturan password: production minimal 12 karakter, campuran huruf besar/kecil, angka, simbol, dan tidak pernah bocor; lokal/test minimal 8 karakter, campuran huruf besar/kecil dan angka

## Role & akses
Tiga jenis pengguna, semua dalam satu tabel `users` dibedakan lewat kolom `role`:
- `admin` — akses semua kelas dan data master
- `guru` — akses terbatas ke jadwal mergajarinya sendiri (`schedules.teacher_id`)
- Wali kelas BUKAN role terpisah — itu guru biasa yang `id`-nya muncul di `classes.homeroom_teacher_id`. Cek dengan relasi, bukan kolom role

Gunakan Laravel **Policy** untuk otorisasi dan **Query Object** untuk scoping query. Jangan hardcode kondisi role di dalam Blade/Livewire view — itu bug, bukan fitur.

Tiga Query Object di `app/Recap/Queries/` — semua mengembalikan struktur array yang sama, dipakai bersama oleh view dan export:
- `HomeroomClassRecapQuery` — wali kelas, scope `classes.homeroom_teacher_id`
- `TeacherSubjectRecapQuery` — guru, scope `schedules.teacher_id`
- `AdminRecapQuery` — admin, semua kelas

## Konvensi struktur kode
- Satu Livewire component per fitur utama (contoh: `AttendanceEntry`, `HomeroomClassRecap`, `ScheduleManagement`) — bukan Controller + Blade view terpisah
- Nama komponen Livewire: PascalCase, deskriptif berdasarkan fungsi, bukan berdasarkan nama tabel mentah
- Model mengikuti nama tabel singular PascalCase standar Laravel (`Student`, `Schedule`, `Attendance`, dst)
- Validasi input taruh di dalam Livewire component (`rules()` method), bukan Form Request terpisah, kecuali logic-nya kompleks
- Penulisan ke database lewat service, bukan langsung dari component — supaya bisa diuji tanpa browser dan tidak bisa bypass policy. Contoh: `app/Services/AttendanceRecorder.php`

## Skema database
Rujuk ke spec §3 dan `docs/erd.MD`. Tujuh tabel domain: `users`, `academic_years`, `classes`, `subjects`, `schedules`, `students`, `attendances`.

Kasus khusus yang mudah salah:
- `classes` **wajib** punya `academic_year_id` — kelas dibuat ulang tiap tahun ajaran, kelas yang sama di tahun berbeda = baris berbeda
- `schedules.day_of_week` adalah **tinyint 1-5** (1=Senin..5=Jumat), bukan string
- `attendances` **tidak** memakai soft delete. Koreksi lewat ubah `status` + isi `updated_by`
- Tabel master (`users`, `students`, `classes`, `subjects`, `schedules`) memakai soft delete. Hapus data master tidak boleh menghapus riwayat absensi
- `classes.homeroom_teacher_id` **nullable** — dashboard wali kelas harus punya empty state

## Menjalankan project
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## Batasan platform: shared hosting cPanel
Deploy ke shared hosting, bukan VPS. Konsekuensinya:
- `QUEUE_CONNECTION=sync` — tidak ada `queue:work`
- Tidak mengandalkan cron/scheduler; v1 tidak punya scheduled task
- `storage:link` bisa diblokir → siapkan fallback disk ke direktori nyata di bawah `public/`
- `APP_TIMEZONE=Asia/Jakarta` **wajib**. Default Laravel UTC bisa membuat `date('Y-m-d')` bergeser sehari saat absensi pagi
- `APP_DEBUG=false` di production; `APP_KEY` tidak boleh berubah setelah ada user

## Yang harus diperhatikan
- Query yang scope-nya sensitif terhadap role (terutama query "Kelas saya" milik wali kelas) harus difilter di level query builder, bukan difilter di frontend/view
- `HomeroomClassRecapQuery` **wajib** ikut memfilter `academic_year_id`. Karena kelas dibuat ulang tiap tahun, seorang wali kelas bisa jadi wali dari dua baris `classes` (ganjil + genap)
- Kueri tidak boleh mengasumsikan "pasti satu kelas" — kalau mengembalikan lebih dari satu, UI menampilkan pemilih kelas
- Satu siswa hanya boleh punya satu catatan absensi per jadwal per tanggal — sudah dijaga lewat unique constraint di migration `attendances`, jangan bypass constraint ini di level aplikasi
- Penyimpanan absensi lewat `updateOrCreate` dalam **satu database transaction**. Kalau satu siswa gagal, seluruh batch rollback
- Guru boleh edit absensi **jadwal yang dia ajar** (bukan `recorded_by`-nya), maksimal **1 hari ke belakang**. Admin bebas kapan saja
- Filter periode bulanan bukan hanya `WHERE date BETWEEN` — periode harus **divalidasi terhadap rentang `academic_years`**. Pilih "Juli" saat tahun ajaran mulai Agustus harus error, bukan tabel kosong
- `attendances.recorded_by` boleh berbeda dari `schedules.teacher_id` (kasus guru pengganti) — jangan menganggap selalu sama
- Urutan pengerjaan: **policy diuji dulu, baru component**. Tidak ada component yang ditulis sebelum scoping-nya terverifikasi lewat test
- Fitur di luar scope v1 (login orang tua, notifikasi WA, QR/face recognition) TIDAK dikerjakan — ada di spec §10
