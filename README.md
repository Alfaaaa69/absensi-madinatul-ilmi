# Sistem Absensi Sekolah

Sistem absensi harian untuk satu sekolah mitra PKM. Guru mencatat kehadiran siswa per jam pelajaran, wali kelas memantau kelasnya lintas mata pelajaran, admin mengelola data master dan laporan.

- **Stack:** Laravel 12 + Livewire 3, MySQL, Tailwind CSS 4, Flux UI
- **Deploy:** shared hosting cPanel (`QUEUE_CONNECTION=sync`, tidak ada cron, tidak ada queue worker)
- **Status:** Plan 1-5 sudah dikerjakan; perluasan arsip & hari Sabtu tergantung keputusan sekolah
- **Test:** PHPUnit 12, 518 test, seluruhnya hijau

Dokumen sumber kebenaran ada di `docs/`. Kalau ada konflik antara kode dan dokumen, dokumen menang — baca `CLAUDE.md` dulu sebelum mengubah apa pun.

---

## Kebutuhan sistem

| | Versi |
|---|---|
| PHP | **8.3 ke atas** (WAJIB) |
| Composer | 2 |
| Node.js | 22 |
| MySQL | 8 |

PHP di bawah 8.3 tidak bisa jalan. Proyek ini sengaja tidak punya fallback ke Laravel 12 — kalau cPanel hanya menyediakan PHP 8.2, naikkan versi PHP-nya, jangan turunkan stack-nya. Detailnya di spec §2 D16.

`SESSION_DRIVER=database` di `.env.example` berarti tabel `sessions` harus sudah ada sebelum halaman web dibuka. Kalau muncul error `Unknown database` atau `Table 'sessions' doesn't exist`, migrasi belum dijalankan.

---

## Menjalankan secara lokal

```bash
git clone https://github.com/Alfaaaa69/absensi-madinatul-ilmi.git
cd absensi-madinatul-ilmi

composer install
cp .env.example .env
php artisan key:generate
```

Buat database MySQL kosong bernama `absensi` (UTF-8), lalu isi kredensialnya di `.env`:

```bash
mysql -u root -e "CREATE DATABASE absensi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan migrasi dan seeder:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Buka `http://localhost:8000`.

Atau sekali jalan dengan script bawaan starter kit:

```bash
composer setup
```

### Akun demo

`php artisan migrate --seed` mengisi data contoh: 1 admin, 10 guru, 7 kelas, 210 siswa, 8 mata pelajaran, 105 jadwal, dan ±3.800 baris absensi pada 7 hari terakhir.

| Peran | NUPTK | Password |
|---|---|---|
| Admin | `100000000000000001` | `password` |
| Guru 1 | `20000000000000001` | `password` |
| Guru 2 | `20000000000000002` | `password` |

**Login pakai NUPTK, bukan email.** Email hanya disimpan sebagai kontak dan tidak dipakai masuk ke sistem.

---

## Menjalankan test

```bash
php artisan test                          # semua test
php artisan test --filter=LoginTest       # satu test
```

Test jalan di SQLite in-memory, jadi tidak butuh MySQL dan tidak menyentuh database pengembangan. `public/build` tidak ikut git — hasil build Tailwind/Flux wajib dibuat dengan `npm run build` di mesin mana pun yang menjalankan aplikasi.

Linting dan analisis statis:

```bash
composer lint          # rapikan format (Pint)
composer lint:check    # cek format tanpa mengubah
composer types:check   # PHPStan
composer ci:check      # ketiganya + test, sama dengan yang jalan di CI
```

---

## Continuous integration

`.github/workflows/tests.yml` jalan otomatis setiap push ke `master` dan setiap pull request. Job-nya menjalankan service MySQL 8.4 supaya `php artisan migrate` benar-benar diuji terhadap database yang dipakai produksi — bukan hanya SQLite. Test-nya sendiri tetap SQLite in-memory.

Status build: <https://github.com/Alfaaaa69/absensi-madinatul-ilmi/actions>

---

## Struktur penting

```
app/
  Actions/          AttemptAuthentication — login via NUPTK
  Attendance/       Queries absensi (jadwal hari ini, kontak admin)
  Enums/            5 enum domain (role, status absensi, hari, dst)
  Exports/          RecapSheet — export Excel
  Imports/          Pembaca & validator Excel siswa
  Livewire/         Satu komponen per fitur utama + Concerns/
  Models/           8 model Eloquent
  Policies/         Otorisasi, 8 policy
  Recap/            Query Object rekap + exporter
  Services/         Semua penulisan database
  Support/          NavLink, ScheduleConflictDetector, SchoolBreaks
database/
  migrations/       15 migration (termasuk tabel jeda istirahat)
  seeders/          DemoSchoolSeeder, seeder jadwal Senin
  factories/
docs/
  PRD-Sistem-Absensi-Sekolah.MD
  erd.MD
  specs/            Spec desain + 5 plan implementasi
resources/views/    Blade untuk setiap komponen Livewire
tests/
  Feature/          30 file test feature
  Unit/
```

---

## Aturan yang tidak boleh dilanggar

Bagian ini bukan saran. Melanggar aturan ini merusak fitur secara diam-diam.

**Otorisasi**
- Scoping query hanya boleh di Policy dan Query Object. Jangan tulis kondisi role di dalam Blade atau method `render()`. Itu bug, bukan fitur.
- Wali kelas **bukan** role. `role` hanya berisi `admin` dan `guru`. Status wali kelas dibaca dari `classes.homeroom_teacher_id` — cek via relasi, bukan kolom.
- Query "Kelas Saya" wajib memfilter `academic_year_id` sekaligus, karena kelas dibuat ulang tiap tahun. Satu wali kelas bisa punya dua baris `classes` (ganjil + genap).

**Penulisan data**
- Semua tulisan database lewat service di `app/Services/`, tidak langsung dari komponen Livewire. Ini yang membuat kode bisa diuji tanpa browser dan tidak bisa melewati policy.
- Absensi disimpan dengan `updateOrCreate` dalam **satu database transaction**. Kalau satu siswa gagal, seluruh batch rollback.
- Satu siswa hanya boleh punya satu catatan per jadwal per tanggal. Sudah dijaga unique constraint di migration — jangan bypass di level aplikasi.
- `attendances` **tidak** memakai soft delete. Koreksi lewat ubah `status` dan isi `updated_by`.

**Data master**
- Tabel master (`users`, `students`, `classes`, `subjects`, `schedules`) memakai soft delete. Hapus data master tidak boleh menghapus riwayat absensi.
- `classes` wajib punya `academic_year_id`. Kelas dibuat ulang tiap tahun ajaran.
- `schedules.day_of_week` adalah tinyint 1-5 (1=Senin, 5=Jumat), bukan nama hari. Minggu tidak masuk enum sebagai penanda hari tidak bekerja; jeda istirahat bawaan hanya Senin-Jumat.
- `attendances.recorded_by` boleh berbeda dari `schedules.teacher_id` (guru pengganti). Jangan menganggap selalu sama.

**Waktu**
- `APP_TIMEZONE=Asia/Jakarta` wajib. Default UTC bisa menggeser tanggal absensi sehari saat jam 7 pagi.
- Tanggal hari ini tidak pernah datang dari browser. Selalu dari Carbon.

**Platform**
- `QUEUE_CONNECTION=sync`. Tidak ada `queue:work` di shared hosting.
- `APP_DEBUG=false` di produksi. `APP_KEY` tidak boleh berubah setelah ada user.

---

## Urutan pengerjaan

Policy diuji dulu, baru komponen. Tidak ada komponen yang ditulis sebelum scoping-nya terverifikasi lewat test.

Feature di luar scope v1 **tidak** dikerjakan: login orang tua, notifikasi WhatsApp, QR atau face recognition. Ada di spec §10.

---

## Rencana yang belum dikerjakan

README memakai tabel status — lihat `docs/specs/plans/` untuk isi masing-masing plan yang dikerjakan dengan skill `superpowers:executing-plans`, jangan mulai mengoding dari spec langsung.

| Plan | Isi | Status |
|---|---|---|
| 1 | Fondasi: 7 tabel, model, policy, query rekap, seeder | selesai |
| 2 | Akun: login NUPTK, user management, dashboard, sidebar | selesai |
| 3 | Data master: tahun ajaran, mapel, kelas, jadwal, siswa, import Excel | selesai |
| 4 | Absensi: `TodaySchedule`, `AttendanceEntry`, batas edit 1 hari | selesai |
| 5 | Rekap & deploy: halaman rekap per peran, export, SOP shared hosting | selesai |

Seluruh plan tertutup 518 test feature/unit.

**Diskusi menggantung:** di luar plan ini ada dua topik yang sudah dibahas tapi ditunda — menghapus fitur arsip data master (soft delete) dan menambah hari Sabtu untuk jadwal. Keputusannya menunggu konfirmasi sekolah: arsenal hapus-permanen berisiko terhadap riwayat absensi, dan Sabtu hanya 2 baris perubahan enum.

---

## Kontak

Proyek PKM. Untuk pertanyaan soal keputusan desain, baca `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md` — di situ ada tabel semua keputusan beserta alasannya.
