# Plan 1 — Fondasi (Data Layer)

> **Untuk agent yang mengerjakan:** WAJIB memakai skill `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan plan ini task per task. Format `- [ ]` dipakai untuk melacak progres.

**Goal:** Membangun seluruh data layer — skema, model, factory, policy, service pencatatan absensi, query rekap, exporter, dan seeder — sehingga Plan 2-5 hanya mengerjakan UI tanpa pernah menyentuh query.

**Architecture:** Tujuh tabel dengan primary key UUID. Empat Laravel Policy menjadi satu-satunya sumber kebenaran otorisasi, dan `AttendanceRecorder` memanggil Policy itu lewat `Gate` — bukan menduplikasi logikanya. Tiga Query Object mengembalikan `RecapResult` yang identik sehingga layar dan file export dijamin berasal dari data yang sama. Semua penulisan database lewat service, tidak pernah langsung dari component.

**Spec:** `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`

---

## Global Constraints

Nilai-nilai ini berasal dari spec dan berlaku untuk **setiap task**. Jangan menyimpang tanpa keputusan manusia.

- **Gate versi: tidak ada fallback (D15 + D16).** Wajib **PHP ≥ 8.3** dan **Laravel 13** dari `laravel/livewire-starter-kit` cabang `main`. Kalau PHP lokal di bawah 8.3, task ini berhenti di Step 1 — jangan lanjut dengan versi lebih lama.
- **Fortify dibuang (D17).** Starter kit membawanya; auth dibangun manual di Plan 2 karena identitas login adalah `nuptk`, bukan email.

- **`APP_TIMEZONE=Asia/Jakarta` wajib di `.env`.** Default Laravel UTC; tanpa ini `date('Y-m-d')` bergeser sehari pada absensi jam 7 pagi.
- **`QUEUE_CONNECTION=sync`.** Tidak ada `queue:work` di shared hosting.
- **Primary key UUID** di semua tabel. Pakai trait `HasUuids`.
- **Wali kelas bukan role.** `role` hanya berisi `admin` atau `guru`. Status wali kelas ditentukan relasi `classes.homeroom_teacher_id`.
- **Scoping hanya di Policy dan Query Object.** Dilarang keras menulis kondisi role di dalam view/component. Itu bug, bukan fitur.
- **Soft delete** di `users`, `students`, `classes`, `subjects`, `schedules`. **Tidak** di `attendances` dan `academic_years`.
- **Penulisan database lewat service**, bukan dari component. Satu-satunya pengecualian di plan ini seeder dan factory.
- **Unique constraint tidak boleh di-bypass.** `attendances` unique `(student_id, schedule_id, date)`; `classes` unique `(academic_year_id, name)`; `students.nisn` unique; `users.nuptk` unique.
- **Test memakai SQLite in-memory** lewat `phpunit.xml` (Task 1). `.env` lokal tetap MySQL dan tidak pernah disentuh test.
- **Semua pesan, label, dan exception dalam bahasa Indonesia.**
- **Tidak ada kode "perbaiki nanti".** Setiap snippet di plan ini adalah kode final yang langsung ditulis ke disk. Kalau test gagal, perbaiki penyebabnya — jangan menyalin versi yang salah lalu menggantinya.

---

## File Structure

Semua file yang dibuat plan ini:

```
app/
  Enums/
    UserRole.php                 # admin | guru
    AttendanceStatus.php         # hadir | izin | sakit | alpa
    StudentGender.php            # L | P
    AcademicPeriod.php           # ganjil | genap
    DayOfWeek.php                # 1..5 (Senin..Jumat)
  Models/
    User.php                     # + nuptk, is_active; relasi homeroomClasses, taughtSchedules
    AcademicYear.php             # + period, start_date, end_date, is_active
    SchoolClass.php              # tabel `classes`; + academic_year_id, homeroom_teacher_id
    Subject.php
    Schedule.php                 # + day_of_week enum
    Student.php                  # + nisn unique, gender
    Attendance.php               # tanpa soft delete; + note, updated_by
  Policies/
    SchedulePolicy.php           # view + recordAttendance
    SchoolClassPolicy.php        # viewRecap — modelnya SchoolClass, bukan Class
    StudentPolicy.php
    AttendancePolicy.php         # update
  Recap/
    RecapPeriod.php              # value object: rentang tanggal tervalidasi
    RecapPeriodOutOfRange.php    # exception
    RecapResult.php              # value object: hasil matrix
    RecapQueries/
      Concerns/BuildsRecapMatrix.php
      HomeroomClassRecapQuery.php
      TeacherSubjectRecapQuery.php
      AdminRecapQuery.php
    RecapExporter.php            # Excel + PDF dari RecapResult yang sama
  Exports/
    RecapSheet.php               # FromArray + WithStyles untuk Laravel Excel
  Services/
    AttendanceRecorder.php       # satu-satunya pintu tulis ke attendances
  Support/
    ScheduleConflictDetector.php # deteksi guru 2 kelas jam sama
database/
  migrations/
    0001_01_01_000000_create_users_table.php   # MODIFIKASI Task 1: sessions saja
    2026_01_01_000001_create_academic_years_table.php
    2026_01_01_000002_create_users_table.php
    2026_01_01_000003_create_subjects_table.php
    2026_01_01_000004_create_classes_table.php
    2026_01_01_000005_create_schedules_table.php
    2026_01_01_000006_create_students_table.php
    2026_01_01_000007_create_attendances_table.php
  factories/
    UserFactory.php  AcademicYearFactory.php  SchoolClassFactory.php
    SubjectFactory.php  ScheduleFactory.php  StudentFactory.php  AttendanceFactory.php
  seeders/
    DatabaseSeeder.php
    DemoSchoolSeeder.php         # 1 admin, 10 guru, 210 siswa, 7 kelas, 8 mapel, 105 jadwal
resources/views/recap/
  print.blade.php                # template PDF
tests/Feature/
  SchemaIntegrityTest.php
  ModelRelationTest.php
  DemoSchoolSeederTest.php
  ScheduleConflictDetectorTest.php
  Policy/
    SchedulePolicyTest.php  SchoolClassPolicyTest.php
    StudentPolicyTest.php   AttendancePolicyTest.php
  Services/
    AttendanceRecorderTest.php
  Recap/
    RecapPeriodTest.php
    HomeroomClassRecapQueryTest.php
    TeacherSubjectRecapQueryTest.php
    AdminRecapQueryTest.php
    RecapExporterTest.php
```

**Berkas starter kit yang dipakai, bukan dibuat plan ini:** `resources/views/layouts/app.blade.php` (dipakai sebagai `<x-layouts.app>`), `resources/views/layouts/app/sidebar.blade.php`, `resources/views/layouts/app/header.blade.php`, `resources/views/layouts/auth.blade.php` + `layouts/auth/card.blade.php` (dipakai halaman login Plan 2), `resources/views/components/app-logo.blade.php`, `resources/views/components/app-logo-icon.blade.php`, `resources/views/components/auth-header.blade.php`, `resources/views/components/auth-session-status.blade.php`, `resources/views/components/desktop-user-menu.blade.php`, komponen ikon Flux (`resources/views/flux/`), dan `resources/views/partials/head.blade.php`. Plan 2-5 memakainya, tidak pernah menggantinya.

Judul halaman lewat **prop**, bukan slot: `<x-layouts.app title="Tahun Ajaran">`. Bukan `<x-slot name="title">`.

**Berkas starter kit yang DIHAPUS di Task 1** — daftar ini persis sama dengan yang dieksekusi di Step 6, diverifikasi terhadap tree `dev-main` (`laravel/livewire-starter-kit@0f62a26`):

| Kelompok | Berkas |
|---|---|
| Fortify | `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Actions/Fortify/ResetUserPassword.php`, `config/fortify.php` |
| Validasi profil | `app/Concerns/PasswordValidationRules.php`, `app/Concerns/ProfileValidationRules.php` |
| Halaman auth & settings | `resources/views/pages/` (seluruhnya), `routes/settings.php`, `resources/views/partials/settings-heading.blade.php` |
| Layout auth yang tidak dipakai | `resources/views/layouts/auth/simple.blade.php`, `resources/views/layouts/auth/split.blade.php` |
| Passkey & 2FA | `database/migrations/2024_01_01_000000_create_passkeys_table.php`, `database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php`, `resources/views/components/passkey-registration.blade.php`, `resources/views/components/passkey-verify.blade.php`, `resources/js/passkeys.js` |
| Logout bawaan | `app/Livewire/Actions/Logout.php` (sidebar menulis form logout sendiri) |
| Scaffolding Chisel | `app/Console/Commands/InstallFeaturesCommand.php`, `chisel.php`, `chisel-paths.php`, `rename_livewire_files.php` |
| Halaman contoh | `resources/views/dashboard.blade.php`, `resources/views/welcome.blade.php`, `resources/views/components/placeholder-pattern.blade.php` |
| Tooling tanpa paket | `phpstan.neon` (PHPStan tidak ada di `require-dev`; config-nya hanya membuat `vendor/bin/phpstan` gagal dengan pesan yang menyesatkan) |

Tidak ada folder `lang/` di starter kit, jadi tidak ada yang perlu dihapus dari sana. Plan 2 yang membuat `lang/id/auth.php`.

**Catatan nama:** tabel `classes` dipetakan ke model `SchoolClass`, bukan `Class`, karena `class` keyword PHP. Tabelnya tetap `classes`.

---

## Task 1: Bootstrap project Laravel

**Files:**
- Create: hasil `composer create-project laravel/livewire-starter-kit:dev-main` (D10)
- Modify: `composer.json` (hapus `laravel/fortify` + `laravel/chisel` dari `require`), `bootstrap/providers.php` — buang `FortifyServiceProvider` (D17)
- Delete: tabel "Berkas starter kit yang DIHAPUS" di atas (`app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/`, `app/Concerns/`, `config/fortify.php`, `app/Livewire/Actions/Logout.php`, `resources/views/pages/`, `resources/views/partials/settings-heading.blade.php`, `resources/views/layouts/auth/simple.blade.php`, `resources/views/layouts/auth/split.blade.php`, dua migration passkey/2FA, dua komponen passkey, `resources/js/passkeys.js`, `app/Console/Commands/InstallFeaturesCommand.php`, `chisel.php`, `chisel-paths.php`, `rename_livewire_files.php`, `resources/views/dashboard.blade.php`, `resources/views/welcome.blade.php`, `resources/views/components/placeholder-pattern.blade.php`, `routes/settings.php`, `phpstan.neon`)
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` — sessions saja
- Modify: `.env`, `.gitignore`, `phpunit.xml`
- Delete: `tests/Feature/Auth/`, `tests/Feature/DashboardTest.php`, `tests/Unit/ExampleTest.php`

**Consumes:** keputusan D10, D15, D16, D17, D18 dari spec §2
**Produces:** project Laravel 13 + Livewire 4 + Flux yang jalan, tanpa Fortify, tanpa tabel `users` yang akan bentrok dengan Task 2, dan test terisolasi ke SQLite.

Task ini hanya menyiapkan panggung. Tidak ada model, tidak ada enum, tidak ada policy. Semua itu Task 2-4.

- [ ] **Step 1: Gate versi PHP - cek dulu, stop bila gagal**

```powershell
php -v
php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . PHP_EOL;"
php -m | Select-String -Pattern "pdo_mysql|mbstring|openssl|tokenizer|xml|curl|zip|gd|fileinfo|intl"
```

**Tiga kondisi yang membuat plan ini berhenti di sini:**

1. `PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION` harus lebih besar atau sama dengan **8.3**. Hentikan di sini kalau 8.2 atau lebih rendah, jangan lanjut. Ini bukan saran — D16 menetapkan ini sebagai batas keras.
2. `pdo_mysql` harus ada. Tanpa itu tidak ada database, dan tidak ada pekerjaan yang bisa dilanjutkan.
3. `zip` harus ada. `RecapExporter` (Task 8) memakai `ZipArchive` untuk memeriksa file XLSX. Kalau `zip` belum aktif, aktifkan lewat `php.ini` — di Laragon: menu PHP → toggle ekstensi → restart Apache. **Jangan lanjut dengan `zip` mati**, karena Task 8 akan gagal jauh setelah semua basis data selesai.

Kalau `php` tidak dikenali sebagai perintah, PHP belum ada di `PATH`. Untuk Laragon, tambahkan path ini di awal sesi PowerShell:

```powershell
$env:Path = "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64;$env:Path"
php -v
```

Path di atas adalah versi yang dipakai saat plan ini ditulis (8.3.33). Kalau installasi kamu berbeda, sesuaikan angkanya — yang penting `php -v` melaporkan 8.3 atau lebih tinggi.

Ekstensi selain `pdo_mysql` dan `zip` boleh-boleh: `intl` hilang hanya berarti nama bulan rekap tampil dalam bahasa Inggris, dan itu nama yang sama dalam bahasa Indonesia untuk semua bulan yang dipakai.

- [ ] **Step 2: Buat project di direktori sementara**

Direktori kerja sudah berisi `docs/`, `CLAUDE.MD`, dan `.git/`, jadi `create-project` tidak bisa menulis langsung ke sana.

```powershell
$tmp = "C:\Users\USER\AppData\Local\Temp\opencode\absensi-app"
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
composer create-project laravel/livewire-starter-kit:dev-main $tmp --no-interaction --no-scripts
```

Expected: `Project skeleton created successfully`. Butuh 3-8 menit karena mengunduh banyak asset.

**`dev-main` itu wajib, bukan pilihan.** Packagist untuk `laravel/livewire-starter-kit` hanya punya dua tag: `v1.0.0` dan `v1.0.1`. Kedua tag itu masih mengunci Laravel 12 dan PHP 8.2, dengan `v1.0.1` pada `"php": "^8.2"` dan `"laravel/framework": "^12.0"`. Perintah tanpa `:dev-main` akan memasang **Laravel 12**, dan gejalanya baru terlihat di Step 9. Branch `main` yang mengunci `"php": "^8.3"` dan `"laravel/framework": "^13.17"`.

Tabel pembeda yang harus dipakai kalau Step 9 nanti menampilkan versi framework yang salah:

| Versi di Packagist | PHP | Laravel | Livewire | PHPUnit | Status |
|---|---|---|---|---|---|
| `v1.0.1` (tag terbaru) | `^8.2` | `^12.0` | Flux + Volt | `^11.5` | **Dilarang** — ini yang didapat kalau `:dev-main` dihapus |
| `dev-main` (default branch) | `^8.3` | `^13.17` | Livewire 4 + Flux + Blaze | `^12.5` | **Yang dipakai plan ini** |

**Kenapa `livewire-starter-kit` dan bukan `laravel/laravel`:** D10 mewajibkan starter kit resmi. Skeleton polos `laravel/laravel` tidak punya Livewire, tidak punya layout, tidak punya komponen Flux — semua yang dipakai Plan 2-5. Memakai skeleton polos berarti menulis ulang apa yang sudah ada di starter kit.

**Kenapa `--no-scripts`:** `composer.json` starter kit punya `post-create-project-cmd` yang menjalankan `artisan install:features` (pilih fitur interaktif milik Chisel), `key:generate`, membuat `database/database.sqlite`, lalu `migrate --graceful` ke SQLite. Semuanya tidak berguna di sini: database kita MySQL, `APP_KEY` harus dibuat di direktori kerja pada Step 9, dan perintah `install:features` interaktif akan menggantung di tengah terminal. `--no-scripts` membuat `create-project` deterministik.

Kalau `composer` gagal dengan error versi PHP, itu bukti bahwa Step 1 tidak dijalankan atau PHP-nya salah. Jangan pintas.

- [ ] **Step 3: Pindahkan isi project ke direktori kerja**

`vendor/` dan `node_modules/` sengaja tidak ikut — diinstall ulang di direktori kerja pada Step 9 supaya `composer install` jalan di tempat yang benar.

`database/database.sqlite` juga tidak ikut. Karena Step 2 memakai `--no-scripts`, file itu tidak pernah dibuat di `$tmp`, jadi tidak ada yang perlu dikecualikan.

```powershell
$dest = "C:\Absensi SMP Madinatul Ilmi"
Get-ChildItem $tmp -Force | Where-Object { $_.Name -notin @('.git', '.gitignore', 'vendor', 'node_modules') } |
    Copy-Item -Destination $dest -Recurse -Force
```

- [ ] **Step 4: Buang Fortify**

D17: Fortify mengasumsikan `email` sebagai identitas login. Sistem ini login lewat `nuptk` (D6). Adaptasi Fortify berarti melawan framework demi dua field. Fortify dihapus total; auth dibangun di Plan 2.

```powershell
Remove-Item "$dest\app\Providers\FortifyServiceProvider.php" -Force
Remove-Item "$dest\app\Actions\Fortify" -Recurse -Force
Remove-Item "$dest\app\Concerns" -Recurse -Force
Remove-Item "$dest\config\fortify.php" -Force
Remove-Item "$dest\app\Livewire\Actions" -Recurse -Force
Remove-Item "$dest\resources\views\pages" -Recurse -Force
Remove-Item "$dest\routes\settings.php" -Force
Remove-Item "$dest\resources\views\welcome.blade.php" -Force
Remove-Item "$dest\resources\js\passkeys.js" -Force
Remove-Item "$dest\resources\views\components\passkey-registration.blade.php" -Force
Remove-Item "$dest\resources\views\components\passkey-verify.blade.php" -Force
Remove-Item "$dest\resources\views\partials\settings-heading.blade.php" -Force
Remove-Item "$dest\database\migrations\2024_01_01_000000_create_passkeys_table.php" -Force
Remove-Item "$dest\database\migrations\2025_08_14_170933_add_two_factor_columns_to_users_table.php" -Force
Remove-Item "$dest\tests\Feature\Auth" -Recurse -Force
Remove-Item "$dest\tests\Feature\Settings" -Recurse -Force
Remove-Item "$dest\tests\Feature\DashboardTest.php" -Force
Remove-Item "$dest\tests\Feature\ExampleTest.php" -Force
Remove-Item "$dest\tests\Unit\ExampleTest.php" -Force
Remove-Item "$dest\app\Console\Commands\InstallFeaturesCommand.php" -Force
Remove-Item "$dest\chisel.php" -Force
Remove-Item "$dest\chisel-paths.php" -Force
Remove-Item "$dest\rename_livewire_files.php" -Force
Remove-Item "$dest\resources\views\dashboard.blade.php" -Force
Remove-Item "$dest\resources\views\components\placeholder-pattern.blade.php" -Force
Remove-Item "$dest\resources\views\layouts\auth\simple.blade.php" -Force
Remove-Item "$dest\resources\views\layouts\auth\split.blade.php" -Force
Remove-Item "$dest\phpstan.neon" -Force
```

Tambahkan `-ErrorAction SilentlyContinue` kalau PowerShell complain soal berkas yang memang tidak ada di `dev-main` — `Remove-Item` pada path yang hilang berhenti dengan error dan menggagalkan sisa baris. Setelah blok ini, jalankan verifikasi di bawah agar tidak ada scaffolding yang tertinggal diam-diam.

**Kenapa tiap kelompok dihapus:**

| Yang dihapus | Alasan |
|---|---|
| `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/`, `config/fortify.php` | Auth pakai `nuptk`, bukan email (D17) |
| `app/Concerns/` | Hanya berisi `PasswordValidationRules` dan `ProfileValidationRules` yang dipakai halaman Fortify |
| `app/Livewire/Actions/Logout.php` | Form logout di sidebar; Plan 2 buat ulang milik sendiri |
| `resources/views/pages/` | 12 halaman auth + settings milik Fortify, termasuk `auth/login.blade.php` dan `auth/register.blade.php` |
| `routes/settings.php` | Register route profile/appearance/security yang tidak ada lagi |
| `resources/views/welcome.blade.php` | 64 KB ilustrasi marketing yang tidak dipakai aplikasi internal |
| `resources/js/passkeys.js` + 2 komponen passkey + `partials/settings-heading.blade.php` | Hanya dipakai halaman 2FA/passkey Fortify |
| 2 migration: `create_passkeys_table`, `add_two_factor_columns_to_users_table` | Milik Fortify. Tidak ada fitur v1 yang memakainya |
| `tests/Feature/Auth/` (6 file) | Semua test itu test Fortify: `AuthenticationTest`, `EmailVerificationTest`, `PasswordConfirmationTest`, `PasswordResetTest`, `RegistrationTest`, `TwoFactorChallengeTest` |
| `tests/Feature/Settings/` (2 file) | `ProfileUpdateTest` dan `SecurityTest` menguji halaman settings yang dihapus |
| `tests/Feature/DashboardTest.php`, `ExampleTest.php`, `tests/Unit/ExampleTest.php` | Test placeholder starter kit. Dashboard dibangun ulang di Plan 2 |
| `app/Console/Commands/InstallFeaturesCommand.php`, `chisel.php`, `chisel-paths.php`, `rename_livewire_files.php` | Scaffolding sekali pakai. Meninggalkan `InstallFeaturesCommand` tanpa paket `chisel` akan membuat `artisan` gagal boot saat memuat daftar command |
| `resources/views/dashboard.blade.php` | Halaman dashboard versi Blade milik starter kit. Plan 2 menggantinya dengan komponen Livewire `Dashboard`, jadi blade ini tidak akan pernah dirender |
| `resources/views/components/placeholder-pattern.blade.php` | Dipakai hanya oleh `welcome.blade.php` yang sudah dihapus |
| `resources/views/layouts/auth/simple.blade.php`, `split.blade.php` | Varian layout login yang tidak dipakai. `layouts/auth/card.blade.php` tetap dipakai lewat `auth-header` |
| `phpstan.neon` | PHPStan tidak ada di `require-dev` starter kit. Config yatim ini hanya membuat `vendor/bin/phpstan` gagal dengan pesan yang menyesatkan |

**Yang TIDAK boleh dihapus** (dipakai Plan 2-5): `resources/views/layouts/app.blade.php`, `layouts/app/sidebar.blade.php`, `layouts/app/header.blade.php`, `layouts/auth.blade.php`, `layouts/auth/card.blade.php`, `resources/views/components/auth-header.blade.php`, `auth-session-status.blade.php`, `desktop-user-menu.blade.php`, `app-logo.blade.php`, `app-logo-icon.blade.php`, `resources/views/flux/`, `resources/views/partials/head.blade.php`, `database/factories/UserFactory.php`, `tests/TestCase.php`, `pint.json`.

`desktop-user-menu.blade.php` memanggil `auth()->user()->initials()` dan `route('profile.edit')`. Method `initials()` sudah ada di model `User` (Task 3); route `profile.edit` diganti Plan 2.

Verifikasi bahwa tidak ada scaffolding yang tertinggal:

```powershell
Get-ChildItem $dest -Recurse -Include "chisel*.php","rename_livewire_files.php","phpstan.neon","*passkey*","*two_factor*","fortify.php","FortifyServiceProvider.php" -File |
    Select-Object -ExpandProperty FullName
```

Expected: **tidak ada output.** Kalau ada yang muncul, hapus manual dan ulangi — sisa scaffolding tidak merusak apa pun sekarang, tapi akan menggigit saat `composer install` berikutnya membangun ulang cache class atau saat ada yang menjalankan `php artisan list`.

Ganti **seluruh isi** `bootstrap/providers.php`:

```php
<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
];
```

Sekalian buang dua dependency dari blok `require` di `composer.json`. **Edit `composer.json` secara manual, jangan pakai `composer remove`.**

```json
"require": {
    "php": "^8.3",
    "laravel/framework": "^13.17",
    "laravel/tinker": "^3.0",
    "livewire/blaze": "^1.0",
    "livewire/flux": "^2.13.1",
    "livewire/livewire": "^4.1"
},
```

Dua baris yang dihapus: `"laravel/chisel": "^0.1.0",` dan `"laravel/fortify": "^1.37.2",`. Urutan baris lain dibiarkan seperti bawaan starter kit.

**Kenapa bukan `composer remove`:** `composer.json` starter kit punya script `post-update-cmd` yang memanggil `php artisan install:features`. Perintah `install:features` itu milik `app/Console/Commands/InstallFeaturesCommand.php` yang **baru saja** kita hapus pada blok di atas. `composer remove` memicu `post-update-cmd`, Artisan gagal menemukan perintahnya, dan Composer berhenti dengan error sebelum dependency sempat hilang. Mengedit `composer.json` langsung membuat Step 9 (`composer install`) yang membaca daftar itu, tanpa perlu memicu script apa pun.

`laravel/chisel` dihapus karena itu alat scaffolding milik starter kit (`php artisan install:features`). Kita sudah lewat tahap itu — semua fitur dikunci di spec, dan tidak ada yang perlu dipilih interaktif. `vendor/` belum ada di `$dest` pada tahap ini, jadi `composer remove` juga akan memaksa install penuh hanya untuk membuang dua paket.

- [ ] **Step 5: Hilangkan tabel `users` bawaan (anti-bentrok)**

Migration bawaan starter kit `0001_01_01_000000_create_users_table.php` membuat **tiga** tabel sekaligus: `users`, `password_reset_tokens`, dan `sessions`.

- `users` harus dibuat sendiri di Task 3 dengan kolom `nuptk` dan UUID. Kalau dibiarkan, Task 3 bentrok.
- `sessions` **dibutuhkan**, karena `SESSION_DRIVER=database`.
- `password_reset_tokens` **tidak dipakai** — reset password lewat admin (D8).

Ganti **seluruh isi** file itu dengan:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
```

**`foreignId` wajib diganti `foreignUuid`.** Kolom `user_id` bawaan starter kit memakai `$table->foreignId('user_id')` yang menghasilkan `BIGINT UNSIGNED`. sementara `users.id` di Task 3 adalah `uuid`. Kalau `foreignId` dibiarkan, setiap query session gagal dengan *"Cannot mix incompatible types"* — dan gejalanya muncul saat login, bukan saat migrate, jadi sulit ditelusuri.

`cache` dan `jobs` tetap dibiarkan dari bawaan karena `CACHE_STORE=database` dan `QUEUE_CONNECTION=sync`. Tabel `jobs` tidak pernah dipakai, tapi membiarkannya tidak merusak apa pun, sedangkan menghapus migration bawaan berisiko merusak urutan migration.

- [ ] **Step 6: Konfigurasi `.env`**

```powershell
Copy-Item "$dest\.env.example" "$dest\.env" -Force
```

Lalu edit `$dest\.env`:

```dotenv
APP_NAME="Sistem Absensi Sekolah"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=sync
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local
```

Tiga baris locale **wajib** ada. Tanpa `APP_LOCALE=id`, setiap tanggal, nama bulan, dan pesan validasi akan tampil dalam bahasa Inggris — termasuk pesan error yang dibaca guru.

`QUEUE_CONNECTION=sync` karena tidak ada `queue:work` di shared hosting (spec §7). `SESSION_DRIVER` dan `CACHE_STORE` disetel `database` supaya tidak butuh Redis/memcached.

`DB_CONNECTION=sqlite` dan baris `DB_DATABASE=absensi` yang di-comment di `.env.example` starter kit harus diganti seperti di atas. Kalau dibiarkan, test akan diam-diam memakai file SQLite di disk — persis kesalahan yang Step 7 mencegah.

- [ ] **Step 7: Isolasi test ke SQLite in-memory**

`phpunit.xml` starter kit sudah punya `DB_CONNECTION` dan `DB_DATABASE`. Buka file itu dan pastikan dua baris ini aktif (hapus `<!--` dan `-->` kalau masih dikomentari):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Sama juga, tambahkan locale dan timezone supaya test konsisten dengan produksi:

```xml
<env name="APP_TIMEZONE" value="Asia/Jakarta"/>
<env name="APP_LOCALE" value="id"/>
```

Karena `<env>` di `phpunit.xml` di-set sebelum aplikasi boot dan Dotenv bersifat immutable, test **selalu** memakai SQLite in-memory. `.env` lokal tidak pernah berubah — tidak ada langkah "balik ke MySQL" di plan ini.

- [ ] **Step 8: `.gitignore` - tambahkan satu entri saja**

File `.gitignore` bawaan sudah meng-cover `vendor`, `node_modules`, `public/build`, `/storage/framework/*`, dan `.env`. Yang belum ada dan memang dipakai proyek ini:

```gitignore
/public/uploads
```

Fallback disk ke direktori nyata di bawah `public/` (spec §7) butuh folder ini ada. Jangan timpa file yang sudah ada — append satu baris di akhir.

- [ ] **Step 9: Install dependency dan build asset**

```powershell
composer install
npm install
npm run build
php artisan key:generate
php artisan about
```

Expected: `php artisan about` menampilkan **Laravel 13.x**. Kalau muncul 12.x, Step 2 mengambil tag yang salah — ulangi Step 2.

Kalau `npm run build` gagal dengan error `vite-plus` atau `vp`, paket `optionalDependencies` untuk platform Windows mungkin belum terpasang. Coba `npm install --include=optional`. Kalau tetap gagal, catat di Task 10 dan lanjut — asset bisa dibangun ulang nanti, dan test tidak bergantung pada CSS.

- [ ] **Step 10: Verifikasi tidak ada bentrok dan tidak ada sisa Fortify**

```powershell
php artisan migrate:fresh
php artisan route:list
```

Expected `migrate:fresh`: hanya `sessions` (+ `cache` dan `jobs` dari bawaan) yang dibuat. **Kalau `users` ikut muncul, migration belum terganti** — itu akan bentrok dengan Task 2.

Expected `route:list`: hanya route `home` dan `dashboard` dari starter kit, tanpa `login`, `register`, `forgot-password`, atau `logout`. Route auth kita bangun sendiri di Plan 2.

Cek juga bahwa tidak ada sisa Fortify:

```powershell
Select-String -Path "$dest\app\**\*.php","$dest\config\*.php","$dest\bootstrap\*.php" -Pattern "Fortify|fortify"
```

Expected: tidak ada output. Kalau masih ada, `bootstrap/providers.php` atau `composer.json` belum bersih.

- [ ] **Step 11: Commit**

```powershell
git add -A
git commit -m "chore: bootstrap Laravel 13 + Livewire 4 starter kit, buang Fortify, test SQLite"
```

## Task 2: Enums dan skema 7 tabel

**Files:**
- Create: `app/Enums/{UserRole,AttendanceStatus,StudentGender,AcademicPeriod,DayOfWeek}.php`
- Create: 7 migration `2026_01_01_00000X_*`
- Test: `tests/Feature/SchemaIntegrityTest.php`

**Consumes:** Task 1
**Produces:** 5 enum + 7 tabel. Semua task berikutnya bergantung pada ini.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/SchemaIntegrityTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    private const A = '01900000-0000-7000-8000-00000000000a';
    private const B = '01900000-0000-7000-8000-00000000000b';

    private function seedSupportingRows(string $id): void
    {
        DB::table('academic_years')->insert([
            'id' => $id, 'name' => '2025/2026', 'period' => 'ganjil',
            'start_date' => '2025-07-01', 'end_date' => '2025-12-31',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => $id, 'name' => 'Guru', 'nuptk' => '123456789012345678',
            'password' => bcrypt('rahasia'), 'role' => 'guru', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('subjects')->insert([
            'id' => $id, 'name' => 'Matematika', 'code' => 'MTK',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('classes')->insert([
            'id' => $id, 'academic_year_id' => $id, 'name' => 'VII-A',
            'grade_level' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('schedules')->insert([
            'id' => $id, 'class_id' => $id, 'subject_id' => $id, 'teacher_id' => $id,
            'day_of_week' => 1, 'start_time' => '07:00:00', 'end_time' => '08:30:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('students')->insert([
            'id' => $id, 'class_id' => $id, 'nisn' => '1001', 'name' => 'Budi',
            'gender' => 'L', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_tujuh_tabel_sudah_ada(): void
    {
        foreach ([
            'users', 'academic_years', 'classes', 'subjects',
            'schedules', 'students', 'attendances',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} belum ada");
        }
    }

    public function test_tidak_ada_tabel_bawaan_yang_tidak_dibutuhkan(): void
    {
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_attendances_unik_per_siswa_jadwal_tanggal(): void
    {
        $this->seedSupportingRows(self::A);

        $row = [
            'id' => self::A, 'student_id' => self::A, 'schedule_id' => self::A,
            'date' => '2025-09-01', 'status' => 'hadir', 'recorded_by' => self::A,
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('attendances')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('attendances')->insert($row + ['id' => self::B]);
    }

    public function test_attendances_tidak_pakai_soft_delete(): void
    {
        $this->assertFalse(Schema::hasColumn('attendances', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('academic_years', 'deleted_at'));
    }

    public function test_tabel_master_pakai_soft_delete(): void
    {
        foreach (['users', 'students', 'classes', 'subjects', 'schedules'] as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'deleted_at'),
                "Tabel {$table} seharusnya punya soft delete"
            );
        }
    }

    public function test_homeroom_teacher_id_bisa_kosong(): void
    {
        $this->seedSupportingRows(self::A);

        DB::table('classes')->insert([
            'id' => self::B, 'academic_year_id' => self::A, 'homeroom_teacher_id' => null,
            'name' => 'VII-KOSONG', 'grade_level' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNull(
            DB::table('classes')->where('id', self::B)->value('homeroom_teacher_id')
        );
    }

    public function test_unik_kelas_per_tahun_ajaran(): void
    {
        $this->seedSupportingRows(self::A);

        $this->expectException(QueryException::class);

        DB::table('classes')->insert([
            'id' => self::B, 'academic_year_id' => self::A, 'name' => 'VII-A',
            'grade_level' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_unik_nisn_siswa(): void
    {
        $this->seedSupportingRows(self::A);

        $this->expectException(QueryException::class);

        DB::table('students')->insert([
            'id' => self::B, 'class_id' => self::A, 'nisn' => '1001', 'name' => 'Sari',
            'gender' => 'P', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
```

Test memakai `DB::table`, bukan model, karena model belum ada di task ini.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=SchemaIntegrityTest
```

Expected: FAIL — `Tabel users belum ada`.

- [ ] **Step 3: Tulis 5 enum**

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Guru = 'guru';
}
```

`app/Enums/AttendanceStatus.php`:

```php
<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';
}
```

Tidak ada method `needsNote()` di enum ini. Alasan bersifat opsional untuk semua status (spec D4), jadi tidak ada status yang "butuh" alasan. Kebutuhan alasan diputuskan di form, bukan di enum.

`app/Enums/StudentGender.php`:

```php
<?php

namespace App\Enums;

enum StudentGender: string
{
    case L = 'L';
    case P = 'P';
}
```

`app/Enums/AcademicPeriod.php`:

```php
<?php

namespace App\Enums;

enum AcademicPeriod: string
{
    case Ganjil = 'ganjil';
    case Genap = 'genap';
}
```

`app/Enums/DayOfWeek.php`:

```php
<?php

namespace App\Enums;

enum DayOfWeek: int
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;

    public function label(): string
    {
        return match ($this) {
            self::Senin => 'Senin',
            self::Selasa => 'Selasa',
            self::Rabu => 'Rabu',
            self::Kamis => 'Kamis',
            self::Jumat => 'Jumat',
        };
    }
}
```

- [ ] **Step 4: Tulis 7 migration**

`database/migrations/2026_01_01_000001_create_academic_years_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('period', ['ganjil', 'genap']);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
```

`database/migrations/2026_01_01_000002_create_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('nuptk')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'guru']);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

`database/migrations/2026_01_01_000003_create_subjects_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
```

`database/migrations/2026_01_01_000004_create_classes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignUuid('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->smallInteger('grade_level');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'name']);
            $table->index('grade_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
```

`database/migrations/2026_01_01_000005_create_schedules_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'day_of_week']);
            $table->index(['class_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
```

`database/migrations/2026_01_01_000006_create_students_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('nisn')->unique();
            $table->string('name');
            $table->enum('gender', ['L', 'P']);
            $table->timestamps();
            $table->softDeletes();

            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
```

`database/migrations/2026_01_01_000007_create_attendances_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->date('date');
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa']);
            $table->string('note')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['student_id', 'schedule_id', 'date']);
            $table->index(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
```

Dua catatan yang mudah salah:

- **Tidak ada `softDeletes()`** di `attendances` dan `academic_years`. Koreksi dilakukan dengan mengubah `status` dan mengisi `updated_by`.
- **FK `attendances.student_id` dan `schedule_id` memakai `restrictOnDelete()`, bukan cascade.** Historis absensi tidak boleh hilang. Soft delete sudah mencegah hard delete, dan `restrictOnDelete` menutup kemungkinan kedua.

- [ ] **Step 5: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=SchemaIntegrityTest
```

Expected: 8 tests pass.

Kalau `test_homeroom_teacher_id_bisa_kosong` gagal dengan `NOT NULL constraint failed`, urutan `->nullable()` sebelum `->constrained('users')` di `create_classes_table` terbalik.

- [ ] **Step 6: Commit**

```powershell
git add -A
git commit -m "feat: 5 enum dan skema 7 tabel absensi sekolah"
```

---

## Task 3: Model, relasi, cast, dan factory

**Files:**
- Create: 7 model di `app/Models/`
- Modify: `app/Models/User.php` (ganti seluruh isi)
- Create: 7 factory di `database/factories/`
- Test: `tests/Feature/ModelRelationTest.php`

**Consumes:** enum + tabel dari Task 2
**Produces:** 7 model dan 7 factory. Factory wajib ada di task ini karena **semua** test berikutnya memakai `Model::factory()`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/ModelRelationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\AcademicPeriod;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\StudentGender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModelRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_ditemukan_lewat_relasi_bukan_role(): void
    {
        $wali = User::factory()->create(['role' => UserRole::Guru]);
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);

        $this->assertSame(UserRole::Guru, $wali->fresh()->role);
        $this->assertTrue($wali->isHomeroomOf($kelas));
        $this->assertTrue($wali->homeroomClasses->contains($kelas));
    }

    public function test_schedule_belajar_dari_kelas_subject_dan_guru(): void
    {
        $jadwal = Schedule::factory()->create([
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
        ]);

        $this->assertInstanceOf(SchoolClass::class, $jadwal->schoolClass);
        $this->assertInstanceOf(Subject::class, $jadwal->subject);
        $this->assertInstanceOf(User::class, $jadwal->teacher);
        $this->assertSame(DayOfWeek::Senin, $jadwal->day_of_week);
    }

    public function test_attendance_menyimpan_recorder_dan_updater(): void
    {
        $guru = User::factory()->create();
        $absensi = Attendance::factory()->create([
            'recorded_by' => $guru->id,
            'updated_by' => null,
        ]);

        $this->assertTrue($absensi->recorder->is($guru));
        $this->assertNull($absensi->updater);
    }

    public function test_enum_di_cast_otomatis(): void
    {
        $siswa = Student::factory()->create(['gender' => StudentGender::P]);
        $absensi = Attendance::factory()->create(['status' => AttendanceStatus::Sakit]);
        $tahun = AcademicYear::factory()->create(['period' => AcademicPeriod::Genap]);
        $pengguna = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertSame(StudentGender::P, $siswa->fresh()->gender);
        $this->assertSame(AttendanceStatus::Sakit, $absensi->fresh()->status);
        $this->assertSame(AcademicPeriod::Genap, $tahun->fresh()->period);
        $this->assertSame(UserRole::Admin, $pengguna->fresh()->role);
    }

    public function test_hanya_tabel_master_yang_pakai_soft_delete(): void
    {
        $this->assertContainsUses(SoftDeletes::class, new SchoolClass);
        $this->assertContainsUses(SoftDeletes::class, new Student);
        $this->assertContainsUses(SoftDeletes::class, new Schedule);
        $this->assertNotContainsUses(SoftDeletes::class, new Attendance);
        $this->assertNotContainsUses(SoftDeletes::class, new AcademicYear);
    }

    public function test_walayah_grade_level_tervalidasi(): void
    {
        $this->expectException(ValidationException::class);

        SchoolClass::factory()->create(['grade_level' => 12]);
    }

    public function test_hapus_siswa_tidak_menghapus_absensi(): void
    {
        $absensi = Attendance::factory()->create();
        $absensi->student->delete();

        $this->assertTrue($absensi->student->trashed());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=ModelRelationTest
```

Expected: FAIL — `Model "SchoolClass" not found`.

- [ ] **Step 3: Tulis 7 model**

`app/Models/AcademicYear.php`:

```php
<?php

namespace App\Models;

use App\Enums\AcademicPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'period', 'start_date', 'end_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'period' => AcademicPeriod::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
```

`app/Models/User.php` — ganti seluruh isi:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'nuptk', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Dipakai layout starter kit: resources/views/components/desktop-user-menu.blade.php
     * memanggil auth()->user()->initials(). Tanpa method ini, setiap halaman yang
     * memakai sidebar akan 500.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = '';

        foreach ($parts as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));

            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials === '' ? '?' : $initials;
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    public function taughtSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }

    public function isHomeroomOf(SchoolClass $schoolClass): bool
    {
        return $this->id === $schoolClass->homeroom_teacher_id;
    }
}
```

Tidak ada `HasApiTokens` (Sanctum) dan tidak ada `Notifiable`. Proyek ini tidak punya API dan tidak mengirim notifikasi; menambahkannya hanya menambah dependensi yang tidak pernah dipakai.

`app/Models/SchoolClass.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class SchoolClass extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = ['academic_year_id', 'homeroom_teacher_id', 'name', 'grade_level'];

    protected function casts(): array
    {
        return ['grade_level' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schoolClass): void {
            if (! in_array($schoolClass->grade_level, [7, 8, 9], true)) {
                throw ValidationException::withMessages([
                    'grade_level' => 'Tingkat kelas harus 7, 8, atau 9.',
                ]);
            }
        });
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }
}
```

`SchoolClass` tidak meng-`use MessageBag`. `ValidationException::withMessages()` sudah membuat message bag-nya sendiri, jadi tidak ada import tambahan di file tersebut.

`app/Models/Subject.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'code'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
```

`app/Models/Schedule.php`:

```php
<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
```

`start_time` dan `end_time` di-cast ke `Carbon`. Semua perbandingan waktu di kode berikutnya harus lewat `->format('H:i:s')` atau operator perbandingan Carbon — jangan `strtotime()`.

`app/Models/Student.php`:

```php
<?php

namespace App\Models;

use App\Enums\StudentGender;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['class_id', 'nisn', 'name', 'gender'];

    protected function casts(): array
    {
        return ['gender' => StudentGender::class];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
```

`app/Models/Attendance.php`:

```php
<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['student_id', 'schedule_id', 'date', 'status', 'note', 'recorded_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

**Tidak ada `SoftDeletes` di `Attendance` dan `AcademicYear`.** Koreksi lewat ubah `status` + isi `updated_by`.

- [ ] **Step 4: Tulis 7 factory**

`UserFactory` bawaan Laravel membuat user berbasis email. Ganti seluruh isinya:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nuptk' => '10' . fake()->unique()->numerify('################'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Guru,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function guru(): static
    {
        return $this->state(fn () => ['role' => UserRole::Guru]);
    }
}
```

`nuptk` dibuat 18 digit dan unik per proses test. Factory lama yang memakai `email` sebagai identitas **wajib** dihapus, karena `nuptk` yang jadi identitas login (D6).

`database/factories/AcademicYearFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        return [
            'name' => $tahunMulai . '/' . ($tahunMulai + 1),
            'period' => now()->month >= 7 && now()->month <= 12
                ? AcademicPeriod::Ganjil
                : AcademicPeriod::Genap,
            'start_date' => sprintf('%d-07-01', $tahunMulai),
            'end_date' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_active' => true,
        ];
    }
}
```

Default-nya adalah tahun ajaran yang sedang berjalan. Test yang butuh rentang lain meng-override `start_date`/`end_date`.

`database/factories/SchoolClassFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'homeroom_teacher_id' => User::factory()->state(['role' => UserRole::Guru]),
            'name' => strtoupper(fake()->unique()->bothify('???-##')),
            'grade_level' => 7,
        ];
    }
}
```

`database/factories/SubjectFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $nama = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($nama),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
```

`database/factories/ScheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory()->state(['role' => UserRole::Guru]),
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ];
    }
}
```

`database/factories/StudentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\StudentGender;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'nisn' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(StudentGender::cases()),
        ];
    }
}
```

`database/factories/AttendanceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Sengaja menulis ke DB: siswa harus dibuat di kelas yang sama dengan jadwal,
     * kalau tidak setiap test yang memvalidasi keanggotaan kelas akan gagal
     * karena factory bukan sumber kebenaran relasi.
     */
    public function definition(): array
    {
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        return [
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'note' => null,
            'recorded_by' => $jadwal->teacher_id,
            'updated_by' => null,
        ];
    }
}
```

- [ ] **Step 5: Jalankan test**

```powershell
php artisan test --filter=ModelRelationTest
```

Expected: 7 tests pass.

- [ ] **Step 6: Commit**

```powershell
git add -A
git commit -m "feat: 7 model, relasi, cast enum, dan 7 factory berbasis nuptk"
```

---

## Task 4: Empat Policy otorisasi

**Files:**
- Create: `app/Policies/{SchedulePolicy,SchoolClassPolicy,StudentPolicy,AttendancePolicy}.php`
- Test: `tests/Feature/Policy/{Schedule,SchoolClass,Student,Attendance}PolicyTest.php`

**Consumes:** model + factory dari Task 3
**Produces:** 4 policy yang dipakai `AttendanceRecorder` (Task 6) dan seluruh Livewire component di Plan 2-4.

Aturan ini disalin dari spec §4 dan tidak boleh diubah tanpa keputusan manusia.

- [ ] **Step 1: Tulis test SchedulePolicy**

```php
<?php

namespace Tests\Feature\Policy;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_semua_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();

        $this->assertTrue(app(SchedulePolicy::class)->view($admin, $jadwal));
    }

    public function test_guru_melihat_jadwal_yang_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Rabu,
        ]);

        $this->assertTrue(app(SchedulePolicy::class)->view($guru, $jadwal));
    }

    public function test_guru_tidak_melihat_jadwal_guru_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwalLain = Schedule::factory()->create();

        $this->assertFalse(app(SchedulePolicy::class)->view($guru, $jadwalLain));
    }

    public function test_guru_merekam_absensi_jadwalnya_hari_ini(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->recordAttendance($guru, $jadwal, Carbon::today()));
        $this->assertTrue($policy->recordAttendance($guru, $jadwal, Carbon::today()->subDay()));
        $this->assertFalse($policy->recordAttendance($guru, $jadwal, Carbon::today()->subDays(2)));
        $this->assertFalse($policy->recordAttendance($guru, $jadwal, Carbon::today()->addDay()));
    }

    public function test_guru_tidak_merekam_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create();

        $this->assertFalse(app(SchedulePolicy::class)->recordAttendance($guru, $jadwal, Carbon::today()));
    }

    public function test_admin_merekam_absensi_kapan_saja(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->recordAttendance($admin, $jadwal, Carbon::today()->subYear()));
        $this->assertTrue($policy->recordAttendance($admin, $jadwal, Carbon::today()->addYear()));
    }
}
```

- [ ] **Step 2: Tulis test SchoolClassPolicy**

```php
<?php

namespace Tests\Feature\Policy;

use App\Models\SchoolClass;
use App\Models\User;
use App\Policies\SchoolClassPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_rekap_semua_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();

        $this->assertTrue(app(SchoolClassPolicy::class)->viewRecap($admin, $kelas));
    }

    public function test_wali_kelas_melihat_kelasnya(): void
    {
        $wali = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['homeroom_teacher_id' => $wali->id]);

        $this->assertTrue(app(SchoolClassPolicy::class)->viewRecap($wali, $kelas));
    }

    public function test_guru_biasa_tidak_boleh_lihat_rekap_kelas(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create();

        $this->assertFalse(app(SchoolClassPolicy::class)->viewRecap($guru, $kelas));
    }

    public function test_wali_kelas_tidak_boleh_lihat_kelas_lain(): void
    {
        $wali = User::factory()->guru()->create();
        $kelasLain = SchoolClass::factory()->create();

        $this->assertFalse(app(SchoolClassPolicy::class)->viewRecap($wali, $kelasLain));
    }
}
```

- [ ] **Step 3: Tulis test StudentPolicy**

```php
<?php

namespace Tests\Feature\Policy;

use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mengelola_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = Student::factory()->create();
        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($admin, $siswa));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $siswa));
        $this->assertTrue($policy->delete($admin, $siswa));
    }

    public function test_guru_hanya_baca_siswa_kelas_yang_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Student::factory()->create();

        Schedule::factory()->create([
            'class_id' => $siswa->class_id,
            'teacher_id' => $guru->id,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($guru, $siswa));
        $this->assertFalse($policy->update($guru, $siswa));
        $this->assertFalse($policy->delete($guru, $siswa));
        $this->assertFalse($policy->create($guru));
    }

    public function test_guru_tidak_lihat_siswa_kelas_yang_tidak_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Student::factory()->create();

        $this->assertFalse(app(StudentPolicy::class)->view($guru, $siswa));
    }
}
```

`subject_id` diisi lewat `Subject::factory()` bawaan `Schedule::factory()`. Kalau pernah menulis `subject_id` manual, jail itu harus berisi UUID `subjects` yang benar — bukan UUID user.

- [ ] **Step 4: Tulis test AttendancePolicy**

```php
<?php

namespace Tests\Feature\Policy;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_mengedit_absensi_jadwalnya_hari_ini(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create(['date' => today(), 'schedule_id' => $jadwal->id]);

        $this->assertTrue(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_mengedit_absensi_kemarin(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->subDay(),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertTrue(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_tidak_mengedit_absensi_dua_hari_lalu(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->subDays(2),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_tidak_mengedit_absensi_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->addDay(),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_mengedit_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $absensi = Attendance::factory()->create(['date' => today()]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_admin_mengedit_absensi_kapan_saja(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = app(AttendancePolicy::class);

        foreach ([
            today()->subYear(),
            today()->subDays(2),
            today(),
            today()->addYear(),
        ] as $tanggal) {
            $absensi = Attendance::factory()->create(['date' => $tanggal]);

            $this->assertTrue($policy->update($admin, $absensi));
        }
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=PolicyTest
```

Expected: FAIL — `Target class [App\Policies\SchedulePolicy] does not exist`.

- [ ] **Step 6: Tulis 4 policy**

`app/Policies/SchedulePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;

class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin() || $schedule->teacher_id === $user->id;
    }

    /**
     * Gate untuk membuat baris absensi baru. Baris yang sudah ada
     * diotorisasi lewat AttendancePolicy::update.
     */
    public function recordAttendance(User $user, Schedule $schedule, Carbon $date): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($schedule->teacher_id !== $user->id) {
            return false;
        }

        $today = Carbon::today();

        return $date->greaterThanOrEqualTo($today->copy()->subDay())
            && $date->lessThanOrEqualTo($today);
    }
}
```

`app/Policies/SchoolClassPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewRecap(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin() || $schoolClass->homeroom_teacher_id === $user->id;
    }
}
```

`app/Policies/StudentPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->taughtSchedules()->exists();
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $student->schoolClass->schedules()
            ->where('teacher_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }
}
```

`app/Policies/AttendancePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function update(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return app(SchedulePolicy::class)->recordAttendance(
            $user,
            $attendance->schedule,
            $attendance->date,
        );
    }
}
```

`AttendancePolicy` meneruskan ke `SchedulePolicy::recordAttendance` alih-alih menyalin aturan tanggal. Kalau aturan window berubah, hanya ada **satu** tempat yang harus diubah — bukan dua. Ini yang membuat spec bisa menyebut Policy sebagai satu-satunya sumber kebenaran.

- [ ] **Step 7: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=PolicyTest
```

Expected: 19 tests pass (Schedule 6, SchoolClass 4, Student 3, Attendance 6).

- [ ] **Step 8: Commit**

```powershell
git add -A
git commit -m "feat: 4 policy otorisasi per role dengan 19 test"
```

---

## Task 5: RecapPeriod dengan validasi tahun ajaran

**Files:**
- Create: `app/Recap/RecapPeriod.php`
- Create: `app/Recap/RecapPeriodOutOfRange.php`
- Test: `tests/Feature/Recap/RecapPeriodTest.php`

**Consumes:** `AcademicYear` dari Task 3
**Produces:** `RecapPeriod` yang dipakai ketiga Query Object (Task 7) dan `RecapExporter` (Task 8)

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Recap\RecapPeriod;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecapPeriodTest extends TestCase
{
    public function test_periode_harian_membungkus_satu_hari(): void
    {
        $periode = RecapPeriod::daily(Carbon::parse('2025-09-01'));

        $this->assertSame('daily', $periode->type);
        $this->assertSame('2025-09-01', $periode->start->toDateString());
        $this->assertSame('2025-09-01', $periode->end->toDateString());
    }

    public function test_periode_bulanan_membungkus_sebulan_penuh(): void
    {
        $periode = RecapPeriod::monthly(2025, 9);

        $this->assertSame('monthly', $periode->type);
        $this->assertSame('2025-09-01', $periode->start->toDateString());
        $this->assertSame('2025-09-30', $periode->end->toDateString());
    }

    public function test_september_di_dalam_tahun_ajaran_agustus_juni(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertTrue(RecapPeriod::monthly(2025, 9)->isWithin($tahun));
    }

    public function test_juli_di_luar_tahun_ajaran_yang_mulai_agustus(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertFalse(RecapPeriod::monthly(2025, 7)->isWithin($tahun));
    }

    public function test_januari_tahun_berikutnya_masih_di_dalam_ganjil(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertTrue(RecapPeriod::monthly(2026, 1)->isWithin($tahun));
    }

    public function test_assert_melempar_kalau_di_luar_tahun_ajaran(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->expectException(\App\Recap\RecapPeriodOutOfRange::class);

        RecapPeriod::monthly(2025, 7)->assertWithin($tahun);
    }

    public function test_label_bulanan(): void
    {
        $this->assertSame('September 2025', RecapPeriod::monthly(2025, 9)->label());
    }

    public function test_label_harian(): void
    {
        $this->assertSame('1 September 2025', RecapPeriod::daily(Carbon::parse('2025-09-01'))->label());
    }
}
```

`test_juli_di_luar_tahun_ajaran_yang_mulai_agustus` adalah kasus yang disebut `CLAUDE.MD`: memilih Juli saat tahun ajaran mulai Agustus harus menghasilkan error, bukan tabel kosong. `isWithin()` mengembalikan `false` supaya bisa diuji, dan `assertWithin()` melempar exception supaya komponen Livewire bisa menampilkan pesan error.

Nama bulan dari `translatedFormat()` mengikuti locale Carbon default (`en`), dan nama bulan Bahasa Inggris identik dengan Bahasa Indonesia untuk semua bulan yang dipakai di laporan sekolah. Jadi ekspektasi test tidak berubah meski `ext-intl` tidak terpasang.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=RecapPeriodTest
```

Expected: FAIL — `Class "App\Recap\RecapPeriod" not found`.

- [ ] **Step 3: Tulis exception dan value object**

`app/Recap/RecapPeriodOutOfRange.php`:

```php
<?php

namespace App\Recap;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use RuntimeException;

class RecapPeriodOutOfRange extends RuntimeException
{
    public static function for(RecapPeriod $period, AcademicYear $academicYear): self
    {
        return new self(sprintf(
            'Periode %s berada di luar tahun ajaran %s (%s sampai %s).',
            $period->label(),
            $academicYear->name,
            $academicYear->start_date->translatedFormat('d F Y'),
            $academicYear->end_date->translatedFormat('d F Y'),
        ));
    }

    public static function forClassOutsideYear(SchoolClass $schoolClass, AcademicYear $academicYear): self
    {
        return new self(sprintf(
            'Kelas %s bukan bagian dari tahun ajaran %s.',
            $schoolClass->name,
            $academicYear->name,
        ));
    }
}
```

`app/Recap/RecapPeriod.php`:

```php
<?php

namespace App\Recap;

use App\Models\AcademicYear;
use Illuminate\Support\Carbon;

final class RecapPeriod
{
    private function __construct(
        public readonly string $type,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    public static function daily(Carbon $date): self
    {
        $awal = $date->copy()->startOfDay();

        return new self('daily', $awal, $awal->copy());
    }

    public static function monthly(int $year, int $month): self
    {
        $awal = Carbon::create($year, $month, 1)->startOfDay();

        return new self('monthly', $awal, $awal->copy()->endOfMonth()->startOfDay());
    }

    public function isWithin(AcademicYear $academicYear): bool
    {
        return $this->start->greaterThanOrEqualTo($academicYear->start_date->copy()->startOfDay())
            && $this->end->lessThanOrEqualTo($academicYear->end_date->copy()->endOfDay());
    }

    public function assertWithin(AcademicYear $academicYear): void
    {
        if (! $this->isWithin($academicYear)) {
            throw RecapPeriodOutOfRange::for($this, $academicYear);
        }
    }

    public function label(): string
    {
        return $this->type === 'daily'
            ? $this->start->translatedFormat('d F Y')
            : $this->start->translatedFormat('F Y');
    }
}
```

`end` sengaja di-`startOfDay()`, bukan `endOfDay()`, supaya perbandingan rentang bulanan tidak pernah gagal karena beda jam.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=RecapPeriodTest
```

Expected: 8 tests pass.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: RecapPeriod dengan validasi rentang tahun ajaran"
```

---

## Task 6: AttendanceRecorder

**Files:**
- Create: `app/Services/AttendanceRecorder.php`
- Create: `app/Support/ScheduleConflictDetector.php`
- Test: `tests/Feature/Services/AttendanceRecorderTest.php`
- Test: `tests/Feature/ScheduleConflictDetectorTest.php`

**Consumes:** `Attendance`, `Schedule`, `SchedulePolicy`, `AttendancePolicy` dari Task 3-4
**Produces:** `AttendanceRecorder::record()` — satu-satunya pintu tulis ke `attendances`, dipakai Livewire component di Plan 3

Task ini **tidak** menduplikasi aturan otorisasi. Ia memanggil `Gate` yang memanggil Policy. Kalau guru ditolak, yang dilempar adalah `AuthorizationException`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function rows(array $statusPerSiswa): Collection
    {
        return collect($statusPerSiswa)->map(fn ($status, $studentId) => [
            'student_id' => $studentId,
            'status' => $status,
            'note' => null,
        ])->values();
    }

    public function test_menyimpan_absensi_baru_mengisi_recorded_by(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $jumlah = app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru
        );

        $this->assertSame(1, $jumlah);

        $absensi = Attendance::first();

        $this->assertSame($guru->id, $absensi->recorded_by);
        $this->assertNull($absensi->updated_by);
    }

    public function test_submit_dua_kali_tidak_menggandakan_baris(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);
        $rows = $this->rows([$siswa->id => AttendanceStatus::Hadir]);

        $recorder->record($jadwal, Carbon::today(), $rows, $guru);
        $recorder->record($jadwal, Carbon::today(), $rows, $guru);

        $this->assertSame(1, Attendance::count());
    }

    public function test_koreksi_mengisi_updated_by(): void
    {
        $guru = User::factory()->guru()->create();
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);

        $recorder->record($jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru);
        $recorder->record($jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Sakit]), $admin);

        $absensi = Attendance::first();

        $this->assertSame(AttendanceStatus::Sakit, $absensi->status);
        $this->assertSame($guru->id, $absensi->recorded_by);
        $this->assertSame($admin->id, $absensi->updated_by);
    }

    public function test_guru_tidak_bisa_merekam_tanggal_lampau(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today()->subDays(3), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru
        );
    }

    public function test_guru_tidak_bisa_merekam_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today()->addDay(), collect(), $guru);
    }

    public function test_guru_tidak_bisa_merekam_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), collect(), $guru);
    }

    public function test_admin_merekam_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $jumlah = app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today()->subYear(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $admin
        );

        $this->assertSame(1, $jumlah);
    }

    public function test_siswa_dari_kelas_lain_ditolak(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswaLain = Student::factory()->create();

        $this->expectException(ValidationException::class);

        app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today(), $this->rows([$siswaLain->id => AttendanceStatus::Hadir]), $guru
        );
    }

    public function test_kegagalan_satu_siswa_membatalkan_seluruh_batch(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $sa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $as = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $asing = Student::factory()->create();

        $rows = $this->rows([
            $sa->id => AttendanceStatus::Hadir,
            $asing->id => AttendanceStatus::Hadir,
            $as->id => AttendanceStatus::Hadir,
        ]);

        try {
            app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), $rows, $guru);
        } catch (ValidationException) {
            // diharapkan
        }

        $this->assertSame(0, Attendance::count(), 'Batch harus rollback utuh');
    }

    public function test_catatan_tersimpan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), collect([[
            'student_id' => $siswa->id,
            'status' => AttendanceStatus::Sakit,
            'note' => 'Demam',
        ]]), $guru);

        $this->assertSame('Demam', Attendance::first()->note);
    }

    public function test_baris_lama_menghormati_batas_edit_guru(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);

        $admin = User::factory()->admin()->create();
        $recorder->record($jadwal, Carbon::today()->subDays(5), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $admin);

        $this->expectException(AuthorizationException::class);

        $recorder->record($jadwal, Carbon::today()->subDays(5), $this->rows([$siswa->id => AttendanceStatus::Alpa]), $guru);
    }
}
```

Test terakhir adalah penjaga yang paling mudah bocor: admin boleh mengisi data 5 hari lalu, tapi guru asli **tetap** tidak boleh mengoreksinya — itu konsekuensi dari aturan D5 yang berbasis jadwal, bukan `recorded_by`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AttendanceRecorderTest
```

Expected: FAIL — `Class "App\Services\AttendanceRecorder" not found`.

- [ ] **Step 3: Tulis service**

`app/Services/AttendanceRecorder.php`:

```php
<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AttendanceRecorder
{
    public function record(Schedule $schedule, Carbon $date, Collection $rows, User $actor): int
    {
        Gate::forUser($actor)->authorize('recordAttendance', [$schedule, $date]);

        $idsSiswa = $schedule->schoolClass->students()->pluck('id')->all();

        return (int) DB::transaction(function () use ($schedule, $date, $rows, $actor, $idsSiswa) {
            $tersimpan = 0;

            foreach ($rows as $row) {
                $studentId = $row['student_id'];

                $status = $row['status'] instanceof AttendanceStatus
                    ? $row['status']
                    : AttendanceStatus::from($row['status']);

                if (! in_array($studentId, $idsSiswa, true)) {
                    throw ValidationException::withMessages([
                        'student_id' => "Siswa {$studentId} bukan anggota kelas {$schedule->class_id}.",
                    ]);
                }

                $existing = Attendance::query()
                    ->where('student_id', $studentId)
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($existing === null) {
                    Attendance::create([
                        'student_id' => $studentId,
                        'schedule_id' => $schedule->id,
                        'date' => $date->toDateString(),
                        'status' => $status,
                        'note' => $row['note'] ?? null,
                        'recorded_by' => $actor->id,
                    ]);

                    $tersimpan++;
                } else {
                    $existing->setRelation('schedule', $schedule);

                    Gate::forUser($actor)->authorize('update', $existing);

                    $existing->update([
                        'status' => $status,
                        'note' => $row['note'] ?? null,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            return $tersimpan;
        });
    }
}
```

Tiga hal yang disengaja di sini:

1. **Otorisasi lewat `Gate`, bukan duplikasi logika.** Baris baru lewat `SchedulePolicy::recordAttendance`, baris lama lewat `AttendancePolicy::update`. Service tidak tahu apa pun soal "1 hari ke belakang" — kalau aturan berubah, hanya Policy yang disentuh.
2. **`setRelation('schedule', $schedule)`** mencegah query per baris. Semua baris dalam satu batch milik jadwal yang sama, jadi relasinya di-inject sekali. Tanpa ini, batch 30 siswa = 30 query tambahan.
3. **`find` + `create`/`update`, bukan `updateOrCreate`.** `updateOrCreate` tidak memberi callback yang cukup untuk mengisi `recorded_by` hanya saat insert dan `updated_by` hanya saat koreksi. Unique constraint DB tetap menjadi jaring pengaman kalau ada race condition.

`$tersimpan` hanya menghitung baris **baru**. Koreksi dihitung 0 supaya "3 siswa disimpan" tidak berbohong tentang koreksi.

- [ ] **Step 4: Jalankan test**

```powershell
php artisan test --filter=AttendanceRecorderTest
```

Expected: 11 tests pass.

Kalau `test_kegagalan_satu_siswa_membatalkan_seluruh_batch` gagal dengan jumlah baris 1 atau 2, berarti `DB::transaction` tidak membungkus seluruh loop.

- [ ] **Step 5: Tulis ScheduleConflictDetector**

`app/Support/ScheduleConflictDetector.php`:

```php
<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\User;
use DateTimeInterface;

final class ScheduleConflictDetector
{
    /**
     * @return array<int,Schedule> jadwal yang waktunya bertindih
     */
    public function findConflicts(
        User $teacher,
        int $dayOfWeek,
        string|DateTimeInterface $startTime,
        string|DateTimeInterface $endTime,
        ?string $ignoreScheduleId = null,
    ): array {
        $mulai = $this->toSeconds($startTime);
        $selesai = $this->toSeconds($endTime);

        return Schedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->get()
            ->filter(function (Schedule $schedule) use ($mulai, $selesai) {
                $jadwalMulai = $this->toSeconds($schedule->start_time);
                $jadwalSelesai = $this->toSeconds($schedule->end_time);

                return $mulai < $jadwalSelesai && $jadwalMulai < $selesai;
            })
            ->values()
            ->all();
    }

    private function toSeconds(string|DateTimeInterface $waktu): int
    {
        $jam = $waktu instanceof DateTimeInterface
            ? $waktu->format('H:i:s')
            : (string) $waktu;

        [$jam, $menit, $detik] = array_pad(explode(':', $jam), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
}
```

`start_time` di-cast jadi `Carbon`, jadi `strtotime($schedule->start_time)` akan melempar `TypeError` di PHP 8. `toSeconds()` menerima string maupun objek waktu — pemanggil tidak perlu tahu bentuk datanya.

`tests/Feature/ScheduleConflictDetectorTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use App\Support\ScheduleConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConflictDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function jadwal(User $guru, int $day, string $mulai, string $selesai): Schedule
    {
        return Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => $day,
            'start_time' => $mulai,
            'end_time' => $selesai,
        ]);
    }

    public function test_mendeteksi_jam_bertindih(): void
    {
        $guru = User::factory()->guru()->create();
        $this->jadwal($guru, 1, '07:00:00', '08:30:00');

        $bentrok = app(ScheduleConflictDetector::class)->findConflicts($guru, 1, '08:00:00', '09:00:00');

        $this->assertCount(1, $bentrok);
    }

    public function test_tidak_mendeteksi_janggal_tidak_tumpang_tindih(): void
    {
        $guru = User::factory()->guru()->create();
        $this->jadwal($guru, 1, '07:00:00', '08:30:00');

        $bentrok = app(ScheduleConflictDetector::class)->findConflicts($guru, 1, '08:30:00', '09:30:00');

        $this->assertCount(0, $bentrok);
    }

    public function test_tidak_mendeteksi_hari_berbeda(): void
    {
        $guru = User::factory()->guru()->create();
        $this->jadwal($guru, 1, '07:00:00', '08:30:00');

        $bentrok = app(ScheduleConflictDetector::class)->findConflicts($guru, 2, '07:00:00', '08:30:00');

        $this->assertCount(0, $bentrok);
    }

    public function test_menerima_objek_waktu_dari_model(): void
    {
        $guru = User::factory()->guru()->create();
        $ada = $this->jadwal($guru, 1, '07:00:00', '08:30:00');

        $bentrok = app(ScheduleConflictDetector::class)->findConflicts(
            $guru, 1, $ada->start_time, $ada->end_time, $ada->id
        );

        $this->assertCount(0, $bentrok, 'Jadwal itu sendiri harus diabaikan lewat ignoreScheduleId');
    }
}
```

- [ ] **Step 6: Jalankan test dan commit**

```powershell
php artisan test --filter=ScheduleConflictDetectorTest
```

Expected: 4 tests pass.

```powershell
git add -A
git commit -m "feat: AttendanceRecorder lewat Gate dan deteksi bentrok jadwal"
```

---

## Task 7: Tiga Query Object rekap

**Files:**
- Create: `app/Recap/RecapResult.php`
- Create: `app/Recap/RecapQueries/Concerns/BuildsRecapMatrix.php`
- Create: `app/Recap/RecapQueries/{HomeroomClassRecapQuery,TeacherSubjectRecapQuery,AdminRecapQuery}.php`
- Test: `tests/Feature/Recap/{HomeroomClassRecapQueryTest,TeacherSubjectRecapQueryTest,AdminRecapQueryTest}.php`

**Consumes:** `RecapPeriod` (Task 5), model (Task 3)
**Produces:** tiga query yang mengembalikan `RecapResult` identik, dipakai view dan `RecapExporter`

Task ini tempat spec mengulang aturan scoping dua kali. Salah di sini berarti guru atau wali kelas melihat data kelas lain.

- [ ] **Step 1: Tulis test HomeroomClassRecapQuery**

```php
<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use App\Recap\RecapQueries\HomeroomClassRecapQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeroomClassRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_wali_kelas_hanya_melihat_kelasnya(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $miliknya = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $kelasOrangLain = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($miliknya->id, $hasil->classes->first()->id);
        $this->assertNotSame($kelasOrangLain->id, $hasil->classes->first()->id);
    }

    public function test_kelas_di_tahun_ajaran_berbeda_tidak_ikut_masuk(): void
    {
        $wali = User::factory()->guru()->create();
        $tahunIni = $this->tahun();
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);

        SchoolClass::factory()->create([
            'academic_year_id' => $tahunIni->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'homeroom_teacher_id' => $wali->id,
        ]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahunIni)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($tahunIni->id, $hasil->classes->first()->academic_year_id);
    }

    public function test_menjumlahkan_absensi_per_siswa_per_mapel(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $mapel = Subject::factory()->create();
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $wali->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        foreach ([
            ['2025-09-01', AttendanceStatus::Hadir],
            ['2025-09-02', AttendanceStatus::Hadir],
            ['2025-09-03', AttendanceStatus::Sakit],
            ['2025-09-04', AttendanceStatus::Alpa],
        ] as [$tanggal, $status]) {
            Attendance::create([
                'student_id' => $siswa->id,
                'schedule_id' => $jadwal->id,
                'date' => $tanggal,
                'status' => $status,
                'recorded_by' => $wali->id,
            ]);
        }

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->rows);

        $baris = $hasil->rows->first();
        $sel = $baris['cells'][$mapel->id];

        $this->assertSame(2, $sel['hadir']);
        $this->assertSame(0, $sel['izin']);
        $this->assertSame(1, $sel['sakit']);
        $this->assertSame(1, $sel['alpa']);
        $this->assertSame(4, $sel['total']);
        $this->assertSame(2, $baris['total']['hadir']);
        $this->assertSame(4, array_sum($hasil->summary));
    }

    public function test_absensi_di_luar_periode_tidak_dihitung(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $wali->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-08-25',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $wali->id,
        ]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertSame(0, $hasil->summary['hadir']);
    }

    public function test_periode_di_luar_tahun_ajaran_ditolak(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();

        $this->expectException(RecapPeriodOutOfRange::class);

        app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 7));
    }

    public function test_wali_kelas_lihat_mapel_yang_tidak_diajar_sendiri(): void
    {
        $wali = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $mapelFisika = Subject::factory()->create();
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelFisika->id,
            'teacher_id' => $guruLain->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-09-01',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guruLain->id,
        ]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertTrue($hasil->subjects->contains('id', $mapelFisika->id));
        $this->assertSame(1, $hasil->rows->first()['cells'][$mapelFisika->id]['hadir']);
    }
}
```

- [ ] **Step 2: Tulis test TeacherSubjectRecapQuery**

```php
<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\TeacherSubjectRecapQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSubjectRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_mapel_guru_lain_tidak_masuk_dari_kelas_yang_sama(): void
    {
        $guru = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $mapelGuru = Subject::factory()->create();
        $mapelLain = Subject::factory()->create();

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelGuru->id,
            'teacher_id' => $guru->id,
        ]);
        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelLain->id,
            'teacher_id' => $guruLain->id,
        ]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertCount(1, $hasil->subjects);
        $this->assertSame($mapelGuru->id, $hasil->subjects->first()->id);
    }

    public function test_guru_mengajar_dua_kelas_menghasilkan_satu_baris_per_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelasA = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $kelasB = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $mapel = Subject::factory()->create();

        Schedule::factory()->create(['class_id' => $kelasA->id, 'subject_id' => $mapel->id, 'teacher_id' => $guru->id]);
        Schedule::factory()->create(['class_id' => $kelasB->id, 'subject_id' => $mapel->id, 'teacher_id' => $guru->id]);

        Student::factory()->create(['class_id' => $kelasA->id]);
        Student::factory()->create(['class_id' => $kelasB->id]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(2, $hasil->rows);
        $this->assertCount(2, $hasil->classes);
    }

    public function test_guru_tidak_lihat_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $guruLain = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $guruLain->id,
        ]);
        Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::create([
            'student_id' => Student::first()->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-09-01',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guruLain->id,
        ]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(0, $hasil->rows);
        $this->assertCount(0, $hasil->classes);
    }

    public function test_guru_tanpa_jadwal_tidak_melihat_apapun(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = $this->tahun();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(TeacherSubjectRecapQuery::class)
            ->for($guru, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(0, $hasil->classes);
        $this->assertCount(0, $hasil->subjects);
        $this->assertCount(0, $hasil->rows);
    }
}
```

Test pertama memakai dua kelas yang sama, bukan berbeda kelas, karena itu skenario paling berbahaya: guru dan guru lain mengajar mapel berbeda di kelas yang sama. Kalau filter lupa dipasang, mapel orang lain ikut muncul.

- [ ] **Step 3: Tulis test AdminRecapQuery**

```php
<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use App\Recap\RecapQueries\AdminRecapQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_admin_memilih_kelas_yang_dia_inginkan(): void
    {
        $tahun = $this->tahun();
        $kelasA = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $kelasB = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelasB)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($kelasB->id, $hasil->classes->first()->id);
        $this->assertNotSame($kelasA->id, $hasil->classes->first()->id);
    }

    public function test_admin_tanpa_kelas_terpilih_mengembalikan_semua_kelas(): void
    {
        $tahun = $this->tahun();
        SchoolClass::factory()->count(3)->create(['academic_year_id' => $tahun->id]);

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(3, $hasil->classes);
    }

    public function test_admin_tidak_bisa_memilih_kelas_dari_tahun_ajaran_lain(): void
    {
        $tahun = $this->tahun();
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);
        $kelasLalu = SchoolClass::factory()->create(['academic_year_id' => $tahunLalu->id]);

        $this->expectException(RecapPeriodOutOfRange::class);

        app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelasLalu)
            ->run(RecapPeriod::monthly(2025, 9));
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=RecapQueryTest
```

Expected: FAIL — `Class "App\Recap\RecapQueries\HomeroomClassRecapQuery" not found`.

- [ ] **Step 5: Tulis RecapResult**

`app/Recap/RecapResult.php`:

```php
<?php

namespace App\Recap;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @implements Arrayable<string, mixed>
 */
final class RecapResult implements Arrayable
{
    public function __construct(
        public readonly Collection $classes,
        public readonly RecapPeriod $period,
        public readonly Collection $subjects,
        public readonly Collection $rows,
        public readonly array $summary,
    ) {}

    public function toArray(): array
    {
        return [
            'classes' => $this->classes->toArray(),
            'period' => [
                'type' => $this->period->type,
                'start' => $this->period->start->toDateString(),
                'end' => $this->period->end->toDateString(),
                'label' => $this->period->label(),
            ],
            'subjects' => $this->subjects->toArray(),
            'rows' => $this->rows->toArray(),
            'summary' => $this->summary,
        ];
    }
}
```

- [ ] **Step 6: Tulis trait pengumpul matriks**

`app/Recap/RecapQueries/Concerns/BuildsRecapMatrix.php`:

```php
<?php

namespace App\Recap\RecapQueries\Concerns;

use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait BuildsRecapMatrix
{
    /**
     * @param  Collection<int,\App\Models\SchoolClass>  $classes
     * @param  Collection<int,Schedule>  $schedules
     * @param  Collection<int,Student>|null  $students
     */
    protected function buildResult(
        Collection $classes,
        Collection $schedules,
        RecapPeriod $period,
        ?Collection $students = null,
    ): RecapResult {
        $schedules = $this->filterByDay($schedules, $period);

        $subjects = Subject::query()
            ->whereIn('id', $schedules->pluck('subject_id')->unique())
            ->orderBy('name')
            ->get();

        $siswa = $students
            ? $students->sortBy('name')->values()
            : Student::query()
                ->whereIn('class_id', $classes->pluck('id'))
                ->orderBy('name')
                ->get();

        $jumlah = $this->aggregate($schedules->pluck('id'), $period);

        $summary = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        $rows = [];

        foreach ($siswa as $murid) {
            $cells = [];
            $totalSiswa = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

            foreach ($subjects as $mapel) {
                $jadwalMapel = $schedules
                    ->filter(fn (Schedule $j) => $j->subject_id === $mapel->id && $j->class_id === $murid->class_id);

                $cell = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

                foreach ($jadwalMapel as $jadwal) {
                    foreach ($jumlah["{$murid->id}|{$jadwal->id}"] ?? [] as $status => $banyak) {
                        $cell[$status] += $banyak;
                    }
                }

                $cell['total'] = array_sum($cell);
                $cells[$mapel->id] = $cell;

                foreach ($summary as $status => $jumlahStatus) {
                    $totalSiswa[$status] += $cell[$status];
                    $summary[$status] += $cell[$status];
                }
            }

            $totalSiswa['total'] = array_sum($totalSiswa);

            $rows[] = [
                'student' => $murid,
                'class' => $murid->schoolClass,
                'cells' => $cells,
                'total' => $totalSiswa,
            ];
        }

        return new RecapResult(
            classes: $classes->values(),
            period: $period,
            subjects: $subjects,
            rows: collect($rows),
            summary: $summary,
        );
    }

    /**
     * @param  Collection<int,Schedule>  $schedules
     * @return Collection<int,Schedule>
     */
    private function filterByDay(Collection $schedules, RecapPeriod $period): Collection
    {
        if ($period->type !== 'daily') {
            return $schedules;
        }

        $nomorHari = (int) $period->start->format('N');

        return $schedules
            ->filter(fn (Schedule $j) => $j->day_of_week->value === $nomorHari)
            ->values();
    }

    /**
     * Satu query agregat untuk seluruh matriks, bukan satu query per pasangan
     * siswa-jadwal. `DB::table` dipakai supaya `status` tetap string biasa —
     * kalau lewat Eloquent, nilainya jadi enum dan key array tidak bisa dipakai
     * langsung sebagai index.
     *
     * @param  Collection<int,string>  $scheduleIds
     * @return array<string,array<string,int>>
     */
    private function aggregate(Collection $scheduleIds, RecapPeriod $period): array
    {
        if ($scheduleIds->isEmpty()) {
            return [];
        }

        $baris = DB::table('attendances')
            ->selectRaw('student_id, schedule_id, status, COUNT(*) as jml')
            ->whereIn('schedule_id', $scheduleIds)
            ->whereBetween('date', [
                $period->start->toDateString(),
                $period->end->toDateString(),
            ])
            ->groupBy('student_id', 'schedule_id', 'status')
            ->get();

        $hasil = [];

        foreach ($baris as $row) {
            $hasil["{$row->student_id}|{$row->schedule_id}"][$row->status] = (int) $row->jml;
        }

        return $hasil;
    }
}
```

Tiga keputusan di trait ini yang perlu tidak diubah diam-diam:

1. **`$classes` jadi parameter eksplisit.** Kalau `classes` diturunkan dari `pluck('schoolClass')` milik jadwal, guru yang punya kelas tapi belum punya jadwal akan melihat `classes` kosong — dan UI akan menampilkan "pilih kelas" padahal tidak ada yang perlu dipilih. Kelas yang dipilih selalu yang di-return, apa pun isi jadwalnya.
2. **Satu query agregat, bukan N+1.** Versi lama melakukan satu query per pasangan siswa-jadwal; dengan 210 siswa × 8 mapel itu ribuan query. Index `$jumlah["siswa|jadwal"]["hadir"]` eliminating semua query di dalam loop.
3. **Pakai `DB::table`, bukan model `Attendance`.** Kolom `status` di-cast jadi enum di model, dan `pluck(..., 'status')` akan menghasilkan key enum yang tidak bisa dipakai sebagai index array. Query builder mengembalikan string biasa.

- [ ] **Step 7: Tulis tiga query**

`app/Recap/RecapQueries/HomeroomClassRecapQuery.php`:

```php
<?php

namespace App\Recap\RecapQueries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapResult;
use LogicException;

final class HomeroomClassRecapQuery
{
    use BuildsRecapMatrix;

    private ?User $actor = null;

    private ?AcademicYear $academicYear = null;

    public function for(User $actor, AcademicYear $academicYear): self
    {
        $this->actor = $actor;
        $this->academicYear = $academicYear;

        return $this;
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->actor === null || $this->academicYear === null) {
            throw new LogicException('Panggil for() dengan user dan tahun ajaran sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        $kelas = SchoolClass::query()
            ->where('homeroom_teacher_id', $this->actor->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->with('students')
            ->orderBy('name')
            ->get();

        $jadwal = Schedule::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('subject')
            ->get();

        return $this->buildResult(
            $kelas,
            $jadwal,
            $period,
            $kelas->flatMap(fn (SchoolClass $k) => $k->students),
        );
    }
}
```

`where('academic_year_id', ...)` di query ini **wajib**. Kelas dibuat ulang tiap tahun, jadi seorang wali bisa jadi wali dari dua baris `classes` (ganjil + genap). Tanpa filter ini, rekap ganjil ikut menarik data genap.

`app/Recap/RecapQueries/TeacherSubjectRecapQuery.php`:

```php
<?php

namespace App\Recap\RecapQueries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapResult;
use LogicException;

final class TeacherSubjectRecapQuery
{
    use BuildsRecapMatrix;

    private ?User $actor = null;

    private ?AcademicYear $academicYear = null;

    public function for(User $actor, AcademicYear $academicYear): self
    {
        $this->actor = $actor;
        $this->academicYear = $academicYear;

        return $this;
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->actor === null || $this->academicYear === null) {
            throw new LogicException('Panggil for() dengan user dan tahun ajaran sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        $jadwal = Schedule::query()
            ->where('teacher_id', $this->actor->id)
            ->whereIn('class_id', SchoolClass::query()
                ->where('academic_year_id', $this->academicYear->id)
                ->pluck('id'))
            ->with('subject')
            ->get();

        $kelas = SchoolClass::query()
            ->whereIn('id', $jadwal->pluck('class_id')->unique())
            ->orderBy('name')
            ->get();

        $siswa = Student::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->get();

        return $this->buildResult($kelas, $jadwal, $period, $siswa);
    }
}
```

`app/Recap/RecapQueries/AdminRecapQuery.php`:

```php
<?php

namespace App\Recap\RecapQueries;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use App\Recap\RecapQueries\Concerns\BuildsRecapMatrix;
use App\Recap\RecapResult;
use LogicException;

final class AdminRecapQuery
{
    use BuildsRecapMatrix;

    private ?AcademicYear $academicYear = null;

    private ?SchoolClass $schoolClass = null;

    public function forAcademicYear(AcademicYear $academicYear): self
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    public function forClass(SchoolClass $schoolClass): self
    {
        $this->schoolClass = $schoolClass;

        return $this;
    }

    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->academicYear === null) {
            throw new LogicException('Panggil forAcademicYear() sebelum run().');
        }

        $period->assertWithin($this->academicYear);

        if ($this->schoolClass !== null && $this->schoolClass->academic_year_id !== $this->academicYear->id) {
            throw RecapPeriodOutOfRange::forClassOutsideYear($this->schoolClass, $this->academicYear);
        }

        $kelas = SchoolClass::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->when($this->schoolClass, fn ($query) => $query->whereKey($this->schoolClass->id))
            ->orderBy('name')
            ->get();

        $jadwal = Schedule::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('subject')
            ->get();

        $siswa = Student::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->get();

        return $this->buildResult($kelas, $jadwal, $period, $siswa);
    }
}
```

Pemeriksaan kelas-vs-tahun ajaran dilakukan di dalam `run()`, bukan di `forClass()`, supaya urutan pemanggilan tidak berpengaruh. Kalau dicek di `forClass()`, pemanggil yang memanggil `forClass()` lebih dulu akan lolos tanpa validasi.

- [ ] **Step 8: Jalankan test**

```powershell
php artisan test --filter=RecapQueryTest
```

Expected: 13 tests pass (Homeroom 6, Teacher 4, Admin 3).

Kalau `test_menjumlahkan_absensi_per_siswa_per_mapel` gagal dengan `cells` kosong, periksa `Subject::factory()` benar-benar dipakai oleh `Schedule::factory()` sehingga `$schedules->pluck('subject_id')` tidak kosong.

- [ ] **Step 9: Commit**

```powershell
git add -A
git commit -m "feat: 3 query object rekap dengan scoping per role di query builder"
```

---

## Task 8: RecapExporter

**Files:**
- Create: `app/Recap/RecapExporter.php`
- Create: `app/Exports/RecapSheet.php`
- Create: `resources/views/recap/print.blade.php`
- Test: `tests/Feature/Recap/RecapExporterTest.php`

**Consumes:** `RecapResult` dari Task 7
**Produces:** `toExcel()` dan `toPdf()` yang keduanya membaca `RecapResult` yang sama

- [ ] **Step 1: Pasang library**

```powershell
composer require maatwebsite/excel barryvdh/laravel-dompdf --no-interaction
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Published: `config/excel.php` dan `config/dompdf.php`.

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\RecapExporter;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\AdminRecapQuery;
use App\Recap\RecapResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class RecapExporterTest extends TestCase
{
    use RefreshDatabase;

    private function hasil(): RecapResult
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        Schedule::factory()->create(['class_id' => $kelas->id]);
        Student::factory()->create(['class_id' => $kelas->id]);

        return app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelas)
            ->run(RecapPeriod::monthly(2025, 9));
    }

    public function test_menghasilkan_file_excel_xlsx(): void
    {
        $excel = app(RecapExporter::class)->toExcel($this->hasil());

        $this->assertNotSame('', $excel);
        $this->assertSame('PK', substr($excel, 0, 2), 'XLSX adalah file ZIP, harus diawali PK');

        $path = tempnam(sys_get_temp_dir(), 'rekap') . '.xlsx';
        file_put_contents($path, $excel);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();

        unlink($path);
    }

    public function test_menghasilkan_file_pdf(): void
    {
        $pdf = app(RecapExporter::class)->toPdf($this->hasil());

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
```

XLSX bukan XML. File-nya ZIP, jadi magic bytes-nya `PK` dan isinya harus dibuka dengan `ZipArchive`. Assert `<?xml` akan gagal pada file yang sepenuhnya valid — dan test yang salah seperti ini sering lolos di percobaan pertama karena tidak pernah dijalankan.

- [ ] **Step 3: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=RecapExporterTest
```

Expected: FAIL — `Class "App\Recap\RecapExporter" not found`.

- [ ] **Step 4: Tulis exporter**

`app/Recap/RecapExporter.php`:

```php
<?php

namespace App\Recap;

use App\Exports\RecapSheet;
use App\Models\AcademicYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

final class RecapExporter
{
    public function toExcel(RecapResult $result, ?AcademicYear $tahun = null): string
    {
        return Excel::raw(RecapSheet::for($result, $this->tahun($tahun)), ExcelType::TYPE_XLSX);
    }

    public function toPdf(RecapResult $result, ?AcademicYear $tahun = null): string
    {
        $pdf = Pdf::loadView('recap.print', [
            'result' => $result,
            'tahun' => $this->tahun($tahun),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isRemoteEnabled', false);

        return $pdf->output();
    }

    public function excelFilename(RecapResult $result): string
    {
        return 'rekap-absensi-' . $result->period->start->format('Y-m-d')
            . '-' . $result->period->end->format('Y-m-d') . '.xlsx';
    }

    public function pdfFilename(RecapResult $result): string
    {
        return 'rekap-absensi-' . $result->period->start->format('Y-m-d')
            . '-' . $result->period->end->format('Y-m-d') . '.pdf';
    }

    private function tahun(?AcademicYear $tahun): ?AcademicYear
    {
        return $tahun ?? AcademicYear::query()->where('is_active', true)->first();
    }
}
```

`isRemoteEnabled` dimatikan karena dompdf di shared hosting tidak boleh menarik gambar atau asset dari internet. Selain jauh lebih cepat, ini juga menutup SSRF dari konten yang tidak dipercaya.

- [ ] **Step 5: Tulis sheet Excel**

`app/Exports/RecapSheet.php`:

```php
<?php

namespace App\Exports;

use App\Models\AcademicYear;
use App\Recap\RecapResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecapSheet implements FromArray, WithStyles, WithTitle
{
    private const BARIS_META = 5;

    public function __construct(
        private readonly RecapResult $result,
        private readonly ?AcademicYear $tahun,
    ) {}

    public static function for(RecapResult $result, ?AcademicYear $tahun): self
    {
        return new self($result, $tahun);
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    public function array(): array
    {
        $baris = [
            ['LAPORAN KEHADIRAN SISWA'],
            ['Tahun Ajaran', $this->tahun?->name ?? '-'],
            ['Periode', $this->result->period->label()],
            ['Kelas', $this->result->classes->pluck('name')->implode(', ') ?: '-'],
            [''],
        ];

        $baris[] = $this->header();

        foreach ($this->result->rows as $index => $row) {
            $baris[] = array_merge(
                [
                    $index + 1,
                    $row['student']->nisn,
                    $row['student']->name,
                    $row['class']->name,
                ],
                $this->flattenCells($row),
                [
                    $row['total']['hadir'],
                    $row['total']['izin'],
                    $row['total']['sakit'],
                    $row['total']['alpa'],
                ],
            );
        }

        $baris[] = [''];
        $baris[] = ['RINGKASAN', '', '', ''];

        foreach (['hadir', 'izin', 'sakit', 'alpa'] as $status) {
            $baris[] = [ucfirst($status), $this->result->summary[$status]];
        }

        return $baris;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            self::BARIS_META + 1 => ['font' => ['bold' => true]],
        ];
    }

    private function header(): array
    {
        $header = ['No', 'NISN', 'Nama Siswa', 'Kelas'];

        foreach ($this->result->subjects as $mapel) {
            $header[] = $mapel->name;
        }

        return array_merge($header, ['Hadir', 'Izin', 'Sakit', 'Alpa']);
    }

    private function flattenCells(array $row): array
    {
        $nilai = [];

        foreach ($this->result->subjects as $mapel) {
            $cell = $row['cells'][$mapel->id] ?? null;

            $nilai[] = $cell !== null && $cell['total'] > 0
                ? "{$cell['hadir']}/{$cell['total']}"
                : '-';
        }

        return $nilai;
    }
}
```

`BARIS_META` dipakai sebagai sumber angka baris header supaya styles tidak ikut bergeser kalau baris metadata bertambah.

- [ ] **Step 6: Tulis view PDF**

`resources/views/recap/print.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kehadiran Siswa</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 2px; }
        .meta { font-size: 9px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 4px; text-align: center; }
        th { background: #f0f0f0; }
        td.nama, td.kelas { text-align: left; }
        .ringkasan { margin-top: 12px; font-size: 9px; }
    </style>
</head>
<body>
    <h1>LAPORAN KEHADIRAN SISWA</h1>
    <div class="meta">
        Tahun Ajaran: {{ $tahun?->name ?? '-' }} &nbsp;|&nbsp;
        Periode: {{ $result->period->label() }} &nbsp;|&nbsp;
        Kelas: {{ $result->classes->pluck('name')->implode(', ') ?: '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NISN</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                @foreach ($result->subjects as $mapel)
                    <th>{{ $mapel->name }}</th>
                @endforeach
                <th>Hadir</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Alpa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['student']->nisn }}</td>
                    <td class="nama">{{ $row['student']->name }}</td>
                    <td class="kelas">{{ $row['class']->name }}</td>
                    @foreach ($result->subjects as $mapel)
                        @php $cell = $row['cells'][$mapel->id] ?? null; @endphp
                        <td>
                            @if ($cell !== null && $cell['total'] > 0)
                                {{ $cell['hadir'] }}/{{ $cell['total'] }}
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td>{{ $row['total']['hadir'] }}</td>
                    <td>{{ $row['total']['izin'] }}</td>
                    <td>{{ $row['total']['sakit'] }}</td>
                    <td>{{ $row['total']['alpa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $result->subjects->count() + 4 }}">Tidak ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="ringkasan">
        <strong>Ringkasan:</strong>
        Hadir {{ $result->summary['hadir'] }},
        Izin {{ $result->summary['izin'] }},
        Sakit {{ $result->summary['sakit'] }},
        Alpa {{ $result->summary['alpa'] }}
    </div>
</body>
</html>
```

- [ ] **Step 7: Jalankan test**

```powershell
php artisan test --filter=RecapExporterTest
```

Expected: 2 tests pass.

Kalau test PDF gagal karena font, `ext-gd` harus terpasang. Kalau `ZipArchive` tidak ditemukan, `ext-zip` belum aktif — ini juga sudah dicek di Task 1 Step 1.

- [ ] **Step 8: Commit**

```powershell
git add -A
git commit -m "feat: RecapExporter menghasilkan Excel dan PDF dari RecapResult yang sama"
```

---

## Task 9: Seeder data demo

**Files:**
- Create: `database/seeders/DemoSchoolSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/DemoSchoolSeederTest.php`

**Consumes:** model + factory dari Task 3
**Produces:** data yang cukup untuk menguji seluruh flow guru/wali kelas/admin tanpa input manual

Jumlah yang dihasilkan: 1 admin, 10 guru, 7 kelas, 210 siswa, 8 mapel, 105 jadwal, ±3.000 baris absensi.

Jumlah guru **10**, bukan 5, dan itu wajib: 7 kelas yang belajar bersamaan butuh minimal 7 guru berbeda per jam pelajaran. Dengan 6 guru, selalu ada bentrok dan data demo tidak bisa dipakai menguji form jadwal.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\RecapPeriod;
use Database\Seeders\DemoSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSchoolSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menghasilkan_data_lengkap(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertSame(10, User::where('role', UserRole::Guru)->count());
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
        $this->assertSame(7, SchoolClass::count());
        $this->assertSame(210, Student::count());
        $this->assertSame(8, Subject::count());
        $this->assertSame(105, Schedule::count());
        $this->assertGreaterThan(0, Attendance::count());
    }

    public function test_setiap_kelas_punya_wali_kelas(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(0, SchoolClass::whereNull('homeroom_teacher_id')->count());
    }

    public function test_nisn_unik_seluruh_sekolah(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $this->assertSame(
            Student::count(),
            Student::distinct()->count('nisn'),
            'NISN harus unik global, bukan per kelas'
        );
    }

    public function test_tidak_ada_jadwal_bentrok(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $bentrok = 0;

        foreach (Schedule::all()->groupBy(fn (Schedule $j) => $j->teacher_id . '|' . $j->day_of_week->value) as $grup) {
            $grup = $grup->values();

            for ($a = 0; $a < $grup->count(); $a++) {
                for ($b = $a + 1; $b < $grup->count(); $b++) {
                    $satu = $grup[$a];
                    $dua = $grup[$b];

                    if ($satu->start_time < $dua->end_time && $dua->start_time < $satu->end_time) {
                        $bentrok++;
                    }
                }
            }
        }

        $this->assertSame(0, $bentrok, 'Seeder tidak boleh menghasilkan jadwal bentrok');
    }

    public function test_absensi_hanya_dibuat_di_hari_jadwalnya(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $salah = 0;

        DB::table('attendances')
            ->join('schedules', 'schedules.id', '=', 'attendances.schedule_id')
            ->select('attendances.date', 'schedules.day_of_week')
            ->each(function (object $row): void {
                // Carbon::dayOfWeek memberi 0=Minggu .. 6=Sabtu, sama seperti day_of_week_iso
                if ((int) Carbon::parse($row->date)->dayOfWeek !== (int) $row->day_of_week) {
                    $salah++;
                }
            });

        $this->assertSame(0, $salah, 'Absensi hanya boleh ada di hari jadwalnya');
    }

    public function test_tahun_ajaran_aktif_mencakup_hari_ini(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $tahun = AcademicYear::where('is_active', true)->first();

        $this->assertTrue(now()->between(
            $tahun->start_date->copy()->startOfDay(),
            $tahun->end_date->copy()->endOfDay()
        ));
    }

    public function test_periode_aktif_bisa_dipakai_untuk_rekap(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $tahun = AcademicYear::where('is_active', true)->first();
        $periode = RecapPeriod::monthly(now()->year, now()->month);

        $this->assertTrue($periode->isWithin($tahun));
    }
}
```

Pembagian hari dihitung di PHP, bukan lewat fungsi SQL. `weekday()` hanya ada di MySQL, dan di SQLite fungsi itu memakai penomoran Sunday-first (Minggu = 1), bukan ISO Monday-first seperti `schedules.day_of_week`. Kalau dipakai apa adanya, test ini bisa lolos karena alasan yang keliru. `Carbon::parse($row->date)->dayOfWeek` memberi angka 0 (Minggu) sampai 6 (Sabtu) di kedua database, jadi aman.

Karena itu blok import atas file test memuat `use Carbon\Carbon;` dan `use Illuminate\Support\Facades\DB;`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=DemoSchoolSeederTest
```

Expected: FAIL — seeder belum ada.

- [ ] **Step 3: Tulis seeder**

`database/seeders/DemoSchoolSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriod;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\StudentGender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSchoolSeeder extends Seeder
{
    private const KELAS = [
        ['VII-A', 7], ['VII-B', 7], ['VIII-A', 8],
        ['VIII-B', 8], ['IX-A', 9], ['IX-B', 9], ['IX-C', 9],
    ];

    private const MAPEL = [
        'Matematika' => 'MTK',
        'Bahasa Indonesia' => 'BINDO',
        'Bahasa Inggris' => 'BING',
        'Ilmu Pengetahuan Alam' => 'IPA',
        'Ilmu Pengetahuan Sosial' => 'IPS',
        'Pendidikan Agama' => 'PAI',
        'Pendidikan Fisika' => 'PFIS',
        'Pendidikan Pancasila dan Kewarganegaraan' => 'PPKN',
    ];

    private const JUMLAH_GURU = 10;

    private const SISWA_PER_KELAS = 30;

    private const SLOT = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:00:00', '11:30:00'],
    ];

    public function run(): void
    {
        $tahun = $this->buatTahunAjaran();

        User::create([
            'name' => 'Admin Sekolah',
            'nuptk' => '100000000000000001',
            'email' => 'admin@sekolah.sch.id',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $guru = collect(range(1, self::JUMLAH_GURU))->map(fn (int $i) => User::create([
            'name' => "Guru {$i}",
            'nuptk' => '20' . str_pad((string) $i, 16, '0', STR_PAD_LEFT),
            'email' => "guru{$i}@sekolah.sch.id",
            'password' => 'password',
            'role' => UserRole::Guru,
            'is_active' => true,
        ]));

        $mapel = collect(self::MAPEL)->map(fn (string $nama, string $kode) => Subject::create([
            'name' => $nama,
            'code' => $kode,
        ]));

        $kelas = collect(self::KELAS)->map(fn (array $baris, int $i) => SchoolClass::create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $guru[$i % self::JUMLAH_GURU]->id,
            'name' => $baris[0],
            'grade_level' => $baris[1],
        ]));

        $this->buatJadwal($kelas, $mapel, $guru);
        $this->buatSiswa($kelas);
        $this->buatAbsensi($kelas, $tahun);
    }

    private function buatTahunAjaran(): AcademicYear
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        return AcademicYear::create([
            'name' => $tahunMulai . '/' . ($tahunMulai + 1),
            'period' => now()->month >= 7 && now()->month <= 12
                ? AcademicPeriod::Ganjil
                : AcademicPeriod::Genap,
            'start_date' => sprintf('%d-07-01', $tahunMulai),
            'end_date' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_active' => true,
        ]);
    }

    private function buatJadwal($kelas, $mapel, $guru): void
    {
        foreach ($kelas as $indexKelas => $k) {
            foreach (DayOfWeek::cases() as $indexHari => $hari) {
                foreach (self::SLOT as $indexSlot => $slot) {
                    Schedule::create([
                        'class_id' => $k->id,
                        'subject_id' => $mapel[($indexKelas * 3 + $indexSlot) % $mapel->count()]->id,
                        'teacher_id' => $guru[($indexKelas + $indexHari + $indexSlot) % self::JUMLAH_GURU]->id,
                        'day_of_week' => $hari,
                        'start_time' => $slot[0],
                        'end_time' => $slot[1],
                    ]);
                }
            }
        }
    }

    private function buatSiswa($kelas): void
    {
        $namaDepan = ['Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gilang', 'Hana', 'Indra', 'Jihan'];
        $urut = 0;

        foreach ($kelas as $k) {
            for ($i = 1; $i <= self::SISWA_PER_KELAS; $i++) {
                $urut++;

                Student::create([
                    'class_id' => $k->id,
                    'nisn' => (string) (100000 + $urut),
                    'name' => $namaDepan[($i - 1) % count($namaDepan)] . ' ' . $k->name,
                    'gender' => $i % 2 === 0 ? StudentGender::P : StudentGender::L,
                ]);
            }
        }
    }

    private function buatAbsensi($kelas, AcademicYear $tahun): void
    {
        $selesai = min(now()->copy()->endOfDay(), $tahun->end_date->copy()->endOfDay());
        $mulai = max($tahun->start_date->copy()->startOfDay(), $selesai->copy()->subDays(6));

        if ($mulai->gt($selesai)) {
            return;
        }

        $status = [
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Izin,
            AttendanceStatus::Sakit,
            AttendanceStatus::Alpa,
        ];

        $siswaPerKelas = $kelas->mapWithKeys(fn (SchoolClass $k) => [
            $k->id => Student::where('class_id', $k->id)->get(),
        ]);

        $jadwal = Schedule::whereIn('class_id', $kelas->pluck('id'))->get();

        foreach ($jadwal as $j) {
            $siswa = $siswaPerKelas->get($j->class_id, collect());

            for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
                if ($tanggal->dayOfWeek !== $j->day_of_week->value) {
                    continue;
                }

                $baris = [];

                foreach ($siswa as $index => $murid) {
                    $statusHariIni = $status[($index + $tanggal->day) % count($status)];

                    $baris[] = [
                        'id' => (string) Str::uuid(),
                        'student_id' => $murid->id,
                        'schedule_id' => $j->id,
                        'date' => $tanggal->toDateString(),
                        'status' => $statusHariIni->value,
                        'note' => $statusHariIni === AttendanceStatus::Hadir ? null : 'Data demo',
                        'recorded_by' => $j->teacher_id,
                        'updated_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($baris, 300) as $chunk) {
                    DB::table('attendances')->insert($chunk);
                }
            }
        }
    }
}
```

Tiga hal yang membuat seeder ini tidak menghasilkan data palsu:

1. **Jadwal dijamin tidak bentrok.** Untuk satu hari dan satu slot, `($indexKelas + $indexHari + $indexSlot) % 10` menghasilkan 7 guru berbeda karena 7 nilai berurutan mod 10 tidak pernah bertabrakan. Kalau jumlah guru dikurangi ke 6, kelas ke-1 dan ke-7 akan dapat guru yang sama di jam yang sama.
2. **Absensi hanya dibuat di hari jadwalnya.** `$tanggal->dayOfWeek` dibandingkan dengan `day_of_week` jadwal. Tanpa ini, jadwal hari Senin akan punya absensi hari Selasa juga — dan rekap harian langsung salah.
3. **NISN bernomor global** dengan penghitung `$urut`, bukan `crc32()`. Hash bisa bertabrakan antar kelas dan langsung melanggar unique constraint.

Insert memakai `DB::table()->insert()` per chunk 300 baris. Dengan 105 jadwal × 6 hari, insert satu per baris akan menambah beberapa menit ke test.

- [ ] **Step 4: Jalankan test**

```powershell
php artisan test --filter=DemoSchoolSeederTest
```

Expected: 7 tests pass.

Kalau `test_tidak_ada_jadwal_bentrok` gagal, perhitungan guru di `buatJadwal` sudah diubah — jangan lanjut. Kalau `test_absensi_hanya_dibuat_di_hari_jadwalnya` gagal, perbandingan `dayOfWeek` di `buatAbsensi` hilang.

- [ ] **Step 5: Hubungkan ke DatabaseSeeder**

`database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoSchoolSeeder::class);
    }
}
```

- [ ] **Step 6: Verifikasi di database MySQL lokal**

Login via NUPTK belum ada di plan ini — itu Plan 3. Verifikasi dilakukan langsung ke database:

```powershell
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\Student::count().' siswa / '.App\Models\Schedule::count().' jadwal / '.App\Models\Attendance::count().' absensi / '.App\Models\User::count().' user';"
```

Expected: `210 siswa / 105 jadwal / <RIBU> absensi / 11 user`.

Kalau `migrate:fresh` gagal dengan `Base table or view already exists`, ada migration bawaan yang belum di-handle di Task 1 Step 8.

- [ ] **Step 7: Commit**

```powershell
git add -A
git commit -m "feat: seeder data demo 7 kelas, 210 siswa, 105 jadwal tanpa bentrok"
```

---

## Verifikasi akhir plan

```powershell
php artisan test
```

Target: **semua lulus, 0 gagal, 0 skipped.**

Cakupan spec — setiap aturan harus punya test:

| Persyaratan spec | Covered di |
|---|---|
| Skema 7 tabel + unique constraint | Task 2, `SchemaIntegrityTest` |
| Tidak ada `password_reset_tokens` | Task 1 Step 8, Task 2 `SchemaIntegrityTest` |
| Soft delete selektif | Task 2-3, `SchemaIntegrityTest`, `ModelRelationTest` |
| Wali kelas via relasi, bukan role | Task 3, `ModelRelationTest` |
| Guru hanya jadwal sendiri | Task 4, `SchedulePolicyTest` |
| Guru hanya kelas yang diajar | Task 4, `StudentPolicyTest` |
| Wali kelas hanya kelas sendiri | Task 4, `SchoolClassPolicyTest` |
| Guru tidak bisa edit >1 hari lalu / masa depan | Task 4, `AttendancePolicyTest`, `AttendanceRecorderTest` |
| Admin bebas edit | Task 4, `SchedulePolicyTest`, `AttendancePolicyTest` |
| Otorisasi tidak diduplikasi di service | Task 6, `AttendanceRecorder` lewat `Gate` |
| `RecapPeriod` validasi tahun ajaran | Task 5, `RecapPeriodTest` |
| Batch rollback utuh | Task 6, `AttendanceRecorderTest` |
| `recorded_by` vs `updated_by` | Task 6, `AttendanceRecorderTest` |
| Wali kelas kelas sendiri + filter tahun ajaran | Task 7, `HomeroomClassRecapQueryTest` |
| Wali kelas lihat mapel yang tidak diajar | Task 7, `HomeroomClassRecapQueryTest` |
| Guru hanya mapel yang diajar di kelas yang sama | Task 7, `TeacherSubjectRecapQueryTest` |
| Kelas dari tahun ajaran lain ditolak | Task 7, `AdminRecapQueryTest` |
| Export = sumber data layar | Task 8, `RecapExporterTest` |
| Deteksi bentrok jadwal guru | Task 6, `ScheduleConflictDetectorTest` |
| Seeder tanpa bentrok, NISN unik, absensi sesuai hari | Task 9, `DemoSchoolSeederTest` |

Tidak ada aturan spec yang belum punya task dan test.

---

## Yang TIDAK ada di plan ini

Sengaja ditunda ke plan berikutnya, bukan karena tidak penting:

| Item | Masuk |
|---|---|
| Login via NUPTK, `EnsureUserIsActive`, dashboard, manajemen user | Plan 2 |
| 7 CRUD data master, import Excel | Plan 3 |
| `TodaySchedule`, `AttendanceEntry` | Plan 4 |
| Halaman rekap + tab "Kelas saya" | Plan 5 |
| Backup cron, SOP sekolah, deploy cPanel | Plan 5 |


