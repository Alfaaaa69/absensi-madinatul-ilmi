# Spec — Sistem Absensi Sekolah (PKM)

**Status:** disetujui
**Tanggal:** 2026-09-26
**Supersedes:** sebagian `docs/PRD-Sistem-Absensi-Sekolah.md` dan `docs/erd.MD` (lihat [§11](#11-hubungan-dengan-dokumen-lain))

Spec ini disusun dari review dokumen awal + brainstorming bersama manusia. Dokumen ini adalah sumber kebenaran untuk skema database, role & scoping, dan batasan platform. `PRD` tetap sumber kebenaran untuk **tujuan, ruang lingkup, dan user flow**; `CLAUDE.md` tetap sumber kebenaran untuk **konvensi penulisan kode**.

---

## 1. Ringkasan

Sistem absensi harian untuk satu sekolah mitra PKM. Laravel + Livewire, MySQL, deploy di shared hosting cPanel. Guru input absensi per jam pelajaran, wali kelas memantau kelasnya lintas semua mata pelajaran, admin mengelola data master dan laporan.

Estimasi **20 hari kerja part-time (~4-5 minggu kalender)**.

## 2. Keputusan desain

Setiap keputusan di bawah diambil eksplisit bersama manusia, bukan asumsi.

| # | Keputusan | Alasan |
|---|---|---|
| D1 | Proyek dimulai dari nol (`composer create-project`) | Folder repositori hanya berisi `docs/`; belum ada kode |
| D2 | Kelas **per tahun ajaran** (Model B) — tabel `classes` punya `academic_year_id` | Jadwal & guru pengajar berbeda tiap tahun. Kelas yang sama di tahun berbeda = baris berbeda, sehingga laporan tidak mungkin tercampur |
| D3 | `schedules.day` (string) → `day_of_week` (tinyint 1=Senin..5=Jumat) | String "Senin" butuh tabel mapping dan tidak bisa di-query langsung untuk "jadwal hari ini". Sekolah hanya beroperasi Senin-Jumat, jadi 1-5 sudah pasti.
| D4 | 4 status absensi: `hadir`, `izin`, `sakit`, `alpa` | Tetap sesuai PRD. Alasan (`note`) opsional — memaksa alasan menambah friksi tanpa manfaat sebanding |
| D5 | Guru boleh edit absensi **jadwal yang dia ajar**, maksimal **1 hari ke belakang**. Admin bebas kapan saja | Kesalahan input biasanya ketahuan keesokan pagi. Batas ini menjaga laporan bulanan tetap bisa dipercaya setelah turun ke kepala sekolah |
| D6 | **NUPTK adalah identitas login untuk semua user termasuk admin** | Ada di dokumen resmi guru, hafal, tidak pernah berubah. Email sekolah sering ter-reset. Dikonfirmasi 2026-09-26: admin sekolah **memiliki** NUPTK, jadi satu kolom `nuptk` cukup untuk semua user |
| D7 | Halaman **registrasi dimatikan**; hanya admin yang membuat akun | Sistem tertutup. Mencegah siapa pun mendaftar jadi guru |
| D8 | "Lupa password" dimatikan — reset dilakukan admin dari menu user | 20 user internal, lebih andal daripada email yang sering gagal terkirim |
| D9 | Scoping di **Query Object per use-case**, bukan di dalam Livewire component | Logika scoping dipakai 4 tempat. Ditulis terpisah = pasti akan tidak sinkron, dan PDF bisa berbeda dari layar |
| D10 | Livewire starter kit resmi Laravel, cabang `main` | Sudah berisi layout + Flux UI. Jetstream membawa bloat (API token, teams, 2FA) yang tidak dipakai. Tag `v1.0.1` ditolak karena `minimum-stability: dev` — lihat D16 |
| D11 | 4 fitur tambahan masuk v1: tandai-semua-hadir, import Excel siswa, batas window edit, riwayat per siswa | Tanpa import Excel, 200+ siswa diinput manual. Tanpa batas edit, data bisa berubah-ubah |
| D12 | Deploy ke **shared hosting cPanel** | Keputusan manusia. Mengini batasan di beberapa tempat — lihat [§7](#7-batasan-platform) |
| D13 | `students.gender` dipakai, `NOT NULL`, nilai `L` atau `P` | Laporan sekolah hampir selalu memisahkan L/P. Import Excel memvalidasi kolom ini wajib terisi |
| D14 | `day_of_week` 1-5 (Senin-Jumat) | Sekolah hanya beroperasi Senin-Jumat |
| D15 | Versi Laravel **hanya** `^13.0`, PHP **hanya** ≥ 8.3. Tidak ada fallback | Awalnya D15 menawarkan fallback `PHP 8.2 → Laravel ^12`. Fallback itu **dibatalkan** oleh D16 setelah `composer.json` starter kit resmi diverifikasi. Lihat [§7](#gate-sebelum-koding) |
| D16 | **Fallback ke Laravel 12 dihapus.** PHP < 8.3 = STOP, bukan degrade | `laravel/livewire-starter-kit` `main` mensyaratkan `"php": "^8.3"` + `"laravel/framework": "^13.17"`. Tags lama (`v1.0.1`, Feb 2025) jalan di PHP 8.2 tapi memakai `"minimum-stability": "dev"` — menarik dependensi belum stabil untuk sistem yang harus jalan 4 tahun. Kalau cPanel hanya 8.2, **naikkan PHP-nya**, jangan turunkan stack-nya |
| D17 | **Fortify dibuang**, auth dibangun manual | Fortify mengasumsikan `email` sebagai identitas. Sistem ini login via `nuptk` ([D6](#keputusan-desain)). Menyesuaikan Fortify = melawan framework demi 2 field (NUPTK + password). Manual lebih murah dan tidak membawa 2FA/passkey/profile yang tidak dipakai |
| D18 | Gaya komponen: **class component Livewire** (`app/Livewire/*.php`) + view Blade terpisah | Starter kit `main` (Livewire 4 + Flux) memakai class component, bukan Volt. Blade di `resources/views/livewire/*.blade.php` |

Tidak ada keputusan yang masih terbuka. Semua pertanyaan yang muncul saat desain sudah dijawab dan tercatat di atas.

## 3. Skema database

Tujuh tabel. Semua primary key `uuid`. Semua timestamp.

### `users`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | |
| `nuptk` | string | **unique, NOT NULL** — identitas login semua user |
| `email` | string | unique, **nullable** — kontak/arsip, tidak dipakai login |
| `password` | string | |
| `role` | enum | `admin` \| `guru` |
| `is_active` | bool | default `true` |
| `deleted_at` | timestamp | nullable — soft delete |

Wali kelas **bukan** role. Guru yang diwalikan sebuah kelas adalah guru biasa yang `id`-nya muncul di `classes.homeroom_teacher_id`.

### `academic_years`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | mis. `"2025/2026"` |
| `period` | enum | `ganjil` \| `genap` |
| `start_date` | date | |
| `end_date` | date | |
| `is_active` | bool | default `false` |

Tanpa tabel ini, rekap bulanan tidak punya batas atas/bawah dan data tahun berbeda tercampur.

### `classes`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `academic_year_id` | uuid | FK → `academic_years`, cascade on delete |
| `homeroom_teacher_id` | uuid | FK → `users`, **nullable**, null on delete |
| `name` | string | mis. `"VII-A"` |
| `grade_level` | smallint | 7 / 8 / 9, untuk sorting |
| `deleted_at` | timestamp | nullable |

- **unique** (`academic_year_id`, `name`)
- `homeroom_teacher_id` **nullable** karena kelas boleh dibuat sebelum wali kelas ditunjuk. Konsekuensi UI: dashboard wali kelas harus punya empty state (§5.4).

### `subjects`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `name` | string | |
| `code` | string | nullable |
| `deleted_at` | timestamp | nullable |

### `schedules`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `class_id` | uuid | FK → `classes`, cascade |
| `subject_id` | uuid | FK → `subjects`, cascade |
| `teacher_id` | uuid | FK → `users`, cascade |
| `day_of_week` | tinyint | 1=Senin … 5=Jumat ([D14](#keputusan-desain)) |
| `start_time` | time | |
| `end_time` | time | |
| `deleted_at` | timestamp | nullable |

Satu baris = satu kombinasi kelas + mapel + guru + hari + jam.

**Validasi level aplikasi:** satu guru tidak boleh punya dua jadwal dengan rentang jam yang bertumpang tindih di hari sama. Dicek saat create/update, bukan via DB constraint (sulit di MySQL).

### `students`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `class_id` | uuid | FK → `classes`, cascade |
| `nisn` | string | **unique** |
| `name` | string | |
| `gender` | enum | **NOT NULL**, nilai `L` atau `P` ([D13](#keputusan-desain)) |
| `deleted_at` | timestamp | nullable |

Siswa dipindah kelas dengan **update** `class_id`, bukan baris baru. Absensi historisnya tetap benar karena `attendances` menunjuk `schedules`, bukan `classes`.

### `attendances`

| Kolom | Tipe | Constraint |
|---|---|---|
| `id` | uuid | PK |
| `student_id` | uuid | FK → `students`, cascade |
| `schedule_id` | uuid | FK → `schedules`, cascade |
| `date` | date | |
| `status` | enum | `hadir` \| `izin` \| `sakit` \| `alpa` |
| `note` | string | nullable — alasan |
| `recorded_by` | uuid | FK → `users`, **NOT NULL** |
| `updated_by` | uuid | FK → `users`, **nullable** — NULL saat belum pernah dikoreksi |
| `created_at` / `updated_at` | timestamp | |

- **unique** (`student_id`, `schedule_id`, `date`)
- **index** (`schedule_id`, `date`) — untuk rekap per jadwal per periode
- **TIDAK** memakai soft delete. Ini data transaksi: kesalahan input diperbaiki dengan mengubah `status` (tercatat di `updated_by`), bukan menghapus baris. Menghapus baris akan menghapus jejaknya.

### Prinsip soft delete

| Tabel | Soft delete | Alasan |
|---|---|---|
| `users` | Ya | Guru resign → nonaktifkan, jangan dihapus; absensinya masih menunjuk `recorded_by` |
| `students` | Ya | Siswa pindah/purnawawan → histori absensi tetap utuh |
| `classes` | Ya | Kelas ditutup tiap tahun, bukan dihapus |
| `subjects` | Ya | Mapel dihapus di tengah semester → absensi lama harus tetap ada |
| `schedules` | Ya | Jadwal diubah tengah semester → absensi lama jangan ikut hilang |
| `attendances` | **Tidak** | Transaksi. Koreksi lewat `status` + `updated_by` |
| `academic_years` | **Tidak** | Ditutup lewat `is_active = false` |

## 4. Role, policy & scoping

### Prinsip

Scoping terjadi **hanya** di dalam Query Object dan Policy. Blade/Livewire view tidak pernah menuliskan kondisi role atau `WHERE`. Kalau butuh akses baru, cara tercepat untuk menambahkannya adalah menulis filter di view — itu bug, bukan fitur.

### Policy

| Policy | Aturan |
|---|---|
| `SchedulePolicy::view` | Guru: hanya `teacher_id = user.id`. Admin: semua |
| `ClassPolicy::viewRecap` | Admin: semua kelas. Wali kelas: hanya `homeroom_teacher_id = user.id` **pada tahun ajaran terpilih**. Guru: **tidak punya akses** (dia memakai `TeacherSubjectRecapQuery`) |
| `StudentPolicy::view` | Admin: semua. Guru: read-only siswa di kelas yang dia ajar |
| `StudentPolicy` (mutasi) | Admin saja |
| `AttendancePolicy::update` | Guru: `schedule.teacher_id = user.id` **dan** `today()->subDay() <= date <= today()`. Rentang tertutup — guru tidak bisa mengedit ke masa depan maupun ke masa lalu lebih dari 1 hari. Admin: semua, kapan saja |

Aturan edit berlandasan [D5](#keputusan-desain): basisnya adalah **jadwal yang dia ajar**, bukan `recorded_by`-nya. Ini membolehkan guru asli mengoreksi absensi yang diinput guru pengganti — skenario yang sudah dipertimbangkan di `erd.MD:61` (`recorded_by` bisa berbeda dari `schedules.teacher_id`).

### Query Object

Tiga query, masing-masing mengembalikan **struktur array yang sama**:

```
app/Recap/Queries/HomeroomClassRecapQuery   # wali kelas
app/Recap/Queries/TeacherSubjectRecapQuery  # guru
app/Recap/Queries/AdminRecapQuery          # admin
```

```php
[
    'classes'    => [Class, ...],   // Homeroom & Admin: tepat 1. Guru: bisa > 1
    'period'     => ['type' => 'daily'|'monthly', 'start' => Carbon, 'end' => Carbon,
                     'academic_year' => AcademicYear],
    'subjects'   => [Subject, ...], // yang muncul di kolom matrix
    'rows'       => [
        [
            'student' => Student,
            'class'   => Class,     // kelas siswa ini pada tahun ajaran terpilih
            'cells'   => [ // key = subject_id
                1 => ['hadir' => 12, 'izin' => 0, 'sakit' => 1, 'alpa' => 0, 'total' => 13],
                ...
            ],
            'total'   => ['hadir' => 98, 'izin' => 2, 'sakit' => 3, 'alpa' => 1, 'total' => 104],
        ],
    ],
    'summary'    => ['hadir' => n, 'izin' => n, 'sakit' => n, 'alpa' => n],
]
```

Setiap baris `rows` membawa `class` miliknya sendiri. Untuk `TeacherSubjectRecapQuery` ini penting: seorang guru bisa mengajar Matematika di VII-A **dan** VIII-B, sehingga cells yang terisi untuk seorang siswa dari VII-A berbeda dari siswa VIII-B. Kueri menggabungkan semua kelas yang diajar menjadi **satu tabel dengan satu baris per siswa** — bukan tabel terpisah per kelas — karena `students.class_id` sudah diketahui sehingga kelas setiap baris tidak ambigu. Kolom matriks berisi **mata pelajaran yang guru itu ajar di kelas siswa tersebut**.

`app/Recap/RecapExporter.php` menerima array ini lalu mengeluarkan Excel (Laravel Excel) dan PDF. Karena sumbernya sama, PDF yang dicetak kepala sekolah dijamin identik dengan yang tampil di layar — ini yang melindungi kredibilitas laporan PKM.

### Cakupan tiap role

| | Dashboard | Rekap | Ubah data master | Edit absensi |
|---|---|---|---|---|
| **Admin** | semua kelas | semua kelas, semua mapel | ya | semua, kapan saja |
| **Guru** | jadwal hari ini | **mapel yang diajar saja** | tidak | jadwalnya, maks 1 hari ke belakang |
| **Wali kelas** | jadwal hari ini | **kelasnya, lintas semua mapel** | tidak | jadwalnya, maks 1 hari ke belakang |

Wali kelas yang juga mengajar tidak melihat data dobel: "Jadwal Saya" discope dari `schedules.teacher_id`, "Kelas Saya" discope dari `classes.homeroom_teacher_id`. Dua query berbeda, dua tampilan terpisah.

`HomeroomClassRecapQuery` **harus** memfilter tahun ajaran, bukan hanya `homeroom_teacher_id` — karena kelas dibuat ulang tiap tahun ([D2](#keputusan-desain)), seorang wali kelas secara teknis bisa menjadi wali dari dua baris `classes` (satu ganjil, satu genap). Kueri juga tidak boleh mengasumsikan "pasti satu kelas": kalau mengembalikan lebih dari satu, UI menampilkan pemilih kelas.

### Filter periode

`CLAUDE.md:44` menyebut filter bulanan cukup `WHERE date BETWEEN`. Itu belum cukup. Karena `academic_years` punya `start_date`/`end_date`, pilihan periode harus **divalidasi terhadap rentang tahun ajaran**: kalau pengguna memilih "Juli" sementara tahun ajaran aktif mulai Agustus, sistem harus menampilkan error, bukan tabel kosong yang menyesatkan.

## 5. Komponen & alur

12 komponen Livewire di `app/Livewire/`, mengikuti konvensi `CLAUDE.md:23`.

### 5.1 Daftar komponen

| Component | Dipakai oleh | Isi |
|---|---|---|
| `TodaySchedule` | guru, wali kelas | Jadwal mengajar hari ini + penanda sudah/belum absen |
| `AttendanceEntry` | guru | Form absensi per jadwal — komponen inti |
| `HomeroomClassRecap` | wali kelas | Tab "Kelas saya", filter harian/bulanan, drill-down siswa |
| `TeacherSubjectRecap` | guru | Rekap mapel yang diajar, filter bulanan |
| `UserManagement` | admin | CRUD user, reset password, aktif/nonaktif, assign wali kelas |
| `AcademicYearManagement` | admin | CRUD tahun ajaran/semester, aktif/nonaktif |
| `ClassManagement` | admin | CRUD kelas + assign wali kelas |
| `SubjectManagement` | admin | CRUD mapel |
| `ScheduleManagement` | admin | CRUD jadwal + deteksi bentrok guru |
| `StudentManagement` | admin | CRUD siswa, soft delete/restore |
| `StudentImport` | admin | Upload Excel → preview → validasi → simpan |
| `AdminAttendanceReport` | admin | Laporan semua kelas, filter periode, export |

### 5.2 `AttendanceEntry`

- **Tanggal terkunci** — otomatis dari `now()` pada timezone `Asia/Jakarta`, tidak bisa diubah guru. Jam jadwal ditampilkan besar di atas form; ini mencegah kesalahan paling mahal (salah input tanggal).
- **Default semua "Hadir"** — kasus normal cukup satu klik. Tombol "Tandai Semua Hadir" dan "Tandai Semua Alpa" menutup kasus lain tanpa centang satu-satu.
- **Kolom alasan muncul otomatis** hanya saat status=Izin/Sakit/Alpa (opsional — [D4](#keputusan-desain)).
- **Absent yang sudah pernah diisi** → form menampilkan data lama + tombol "Simpan Perubahan", bukan form kosong.
- **Tidak ada auto-save.** Submit eksplisit (PRD §5). Klik salah di HP guru bisa menimpa absensi yang sudah benar.

### 5.3 Penyimpanan lewat service

Component tidak menulis ke database langsung:

```php
// app/Services/AttendanceRecorder.php
AttendanceRecorder::record(Schedule $schedule, Carbon $date,
                            Collection $rows, User $actor)
```

Tanggung jawabnya, berurutan:
1. Cek `AttendancePolicy` (§4)
2. Cek window 1 hari ke belakang
3. `updateOrCreate` per siswa dalam **satu database transaction**
4. Isi `recorded_by` saat insert, `updated_by` hanya saat koreksi

Kalau satu siswa gagal, **seluruh batch rollback** — bukan 20 tersimpan lalu 1 gagal. Unique constraint (`student_id`, `schedule_id`, `date`) dijaga di level DB per `CLAUDE.md:42` dan tidak boleh di-bypass.

### 5.4 Empty states yang wajib ada

- Guru belum punya jadwal hari ini → "Tidak ada jadwal mengajar hari ini."
- Wali kelas belum diwalikan kelas manapun → "Kamu belum diwalikan kelas. Hubungi admin."
- Guru bukan wali kelas → tab "Kelas Saya" tidak muncul sama sekali (bukan disabled)

## 6. Autentikasi

- Login memakai **NUPTK** ([D6](#keputusan-desain)), bukan email.
- Email opsional, tidak dipakai untuk login.
- **Registrasi dimatikan** ([D7](#keputusan-desain)) — `routes/register.php` dihapus, controller registrasi tidak dipakai.
- **Reset password via admin** ([D8](#keputusan-desain)) — form reset password bawaan dihapus.
- Halaman login menampilkan NUPTK, bukan email. Label: "NUPTK".
- `is_active = false` → login ditolak dengan pesan jelas, bukan error 403.

## 7. Batasan platform

Shared hosting cPanel ([D12](#keputusan-desain)). Penyesuaian wajib:

| Yang ada di VPS | Yang harus diubah |
|---|---|
| `queue:work` | `QUEUE_CONNECTION=sync` — aman, v1 tidak punya background job |
| cron + `schedule:run` | Tidak diandalkan. v1 tidak punya scheduled task |
| symlink bebas | `storage:link` sering diblokir → disk `public` diarahkan langsung ke direktori nyata di bawah `public/`, tanpa symlink |
| SSH | Deploy lewat Git Version Control cPanel, atau upload zip + cPanel Terminal |
| `git pull` | `composer install --no-dev --optimize-autoloader` wajib; `vendor/` 10.000+ file |

`APP_TIMEZONE=Asia/Jakarta` wajib. Default Laravel adalah UTC; tanpa ini `date('Y-m-d')` bisa bergeser satu hari pada absensi jam 7 pagi.

### Gate sebelum koding

Dua hal harus dicek di cPanel **sebelum** satu baris kode ditulis, karena keduanya bisa membatalkan seluruh rencana:

1. **Versi PHP** ([R1](#12-risiko)) — menentukan versi Laravel, Livewire starter kit, dan segalanya ([D15](#keputusan-desain)). **Tidak ada fallback lagi:**

   | PHP | Major Laravel | Starter kit | Alasan |
   |---|---|---|---|
   | **≥ 8.3** | `^13.0` | `laravel/livewire-starter-kit` `main` | Bug-fix sampai Q3 2027, security sampai 2028-03-17. Satu-satunya kombinasi yang dipakai proyek ini. |
   | < 8.3 | — | — | **STOP.** Bukan degraded, tapi berhenti. |

   **Kenapa fallback ke Laravel 12 dihapus (D16).** Dulu ada gate `PHP 8.2 → Laravel ^12`. Gate itu dibatalkan setelah diverifikasi ke `composer.json` starter kit resmi: cabang `main` mensyaratkan `"php": "^8.3"` dan `"laravel/framework": "^13.17"`. Tags lama (`v1.0.1`, Feb 2025) memang jalan di PHP 8.2, tapi memakai `"minimum-stability": "dev"` — artinya menarik dependensi yang belum stabil, untuk sistem yang harus jalan 4 tahun. Starter kit `main` memakai `"minimum-stability": "stable"`.

   Jadi kalau cPanel hanya menyediakan PHP 8.2, jawabannya **bukan** turun ke Laravel 12, tapi **naikkan PHP ke 8.3 lewat MultiPHP Manager** terlebih dahulu. Menjalankan stack yang tidak didukung cPanel akan menghasilkan bug yang muncul paling buruk saat deploy, bukan saat development.

   PHP lokal yang dipakai untuk development: **8.3.33** (Laragon), di `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64`. Harus ada di `PATH`; `ext-zip` wajib aktif untuk `RecapExporter`.
2. **`upload_max_filesize` / `post_max_size`** ([R4](#12-risiko)) — harus cukup untuk file Excel siswa

### Urutan deploy

1. Cek versi PHP → **gate, stop bila < 8.3** (bukan 8.2 — lihat D16)
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. `php artisan config:cache && route:cache && view:cache`
5. `php artisan storage:link` → bila gagal, pakai fallback disk
6. `APP_DEBUG=false`
7. `APP_KEY` — **tidak boleh berubah** setelah ada user
8. Verifikasi limit upload (import Excel) dan export PDF

Dua jebakan: `APP_DEBUG=true` di production membocorkan kredensial database lewat stack trace; `APP_KEY` yang berubah membuat seluruh data terenkripsi tidak terbaca.

### Backup

Cron harian `mysqldump` ke direktori **di luar `public_html`**. Data absensi adalah satu-satunya bukti kerja PKM. Retensi 30 hari.

## 8. Strategi testing

**PHPUnit 12** (ikut bawaan Livewire starter kit branch `dev-main`). Bukan Pest — starter kit `dev-main` tidak menyertakan Pest sama sekali; tag `v1.0.0`/`v1.0.1` memang memakai Pest, tapi keduanya dilarang (D10). Filter test pakai `php artisan test --filter=NamaTest`. Diurutkan dari nilai tertinggi. Manual testing tetap dipakai untuk rendering PDF dan visual — keduanya sulit diuji otomatis dan tidak sepenting.

Test memakai SQLite in-memory, bukan MySQL. Konsekuensinya: test yang bergantung pada fitur khusus MySQL harus ditulis di luar PHPUnit atau diuji manual.

**Pola yang berlaku di semua fase:** policy diuji dulu, baru component. Tidak ada component yang ditulis sebelum scoping-nya terverifikasi lewat test.

### Tier 1 — Scoping

- Guru tidak bisa membuka rekap kelas yang bukan dia wali
- Wali kelas hanya melihat kelasnya pada tahun ajaran terpilih, bukan kelas lain
- Guru hanya melihat jadwal `teacher_id`-nya
- Guru tidak bisa edit absensi jadwal orang lain
- Guru tidak bisa edit absensi lebih dari 1 hari ke belakang maupun ke depan
- Rekap guru hanya berisi mapel yang dia ajar

### Tier 2 — Integritas data

- Submit dua kali untuk siswa + jadwal + tanggal yang sama → tetap 1 baris
- Kalau satu siswa gagal di tengah → seluruh batch rollback
- `recorded_by` terisi saat insert; `updated_by` NULL saat insert, terisi saat koreksi

### Tier 3 — Filter periode

- Pilih "Juli" saat tahun ajaran aktif mulai Agustus → error, bukan tabel kosong
- Rekap bulanan hanya menghitung tanggal dalam rentang tahun ajaran

### Tier 4 — Livewire smoke (`Livewire::test()`)

- Form absensi submit sukses
- "Tandai semua hadir" mengisi seluruh baris
- Drill-down siswa tidak membocorkan data kelas lain

### Tidak diuji di v1

Rendering PDF, tampilan visual, E2E. Dicek manual.

## 9. Fase implementasi

| Fase | Plan | Isi | Hari |
|---|---|---|---|
| **0. Prasyarat** | — | Gate di cPanel: versi PHP (wajib ≥ 8.3) dan limit upload. **Stop bila PHP < 8.3** | 0,5 |
| **1. Fondasi** | `plans/2026-09-26-plan-1-fondasi.md` | `create-project` + starter kit, `.env` (timezone, sync queue), 7 migration, 7 model + relasi + cast, 7 factory, 4 Policy, `AttendanceRecorder`, 3 Query Object, `RecapExporter`, seeder demo | 3,5 |
| **2. Akun** | `plans/2026-09-26-plan-2-akun.md` | Login via NUPTK, registrasi dimatikan, `UserManagement` (CRUD, reset password, aktif/nonaktif, assign wali kelas) | 2 |
| **3. Data master** | `plans/2026-09-26-plan-3-data-master.md` | `AcademicYearManagement`, `ClassManagement`, `SubjectManagement`, `ScheduleManagement` (+ deteksi bentrok), `StudentManagement`, `StudentImport` | 4 |
| **4. Absensi** | `plans/2026-09-26-plan-4-absensi.md` | `TodaySchedule`, `AttendanceEntry` | 3 |
| **5. Rekap & deploy** | `plans/2026-09-26-plan-5-rekap-dan-deploy.md` | `HomeroomClassRecap`, `TeacherSubjectRecap`, `AdminAttendanceReport`, drill-down siswa, export Excel/PDF, hardening, SOP deploy | 5 |

Total 18 hari kerja part-time, ~4 minggu kalender.

**Urutan plan ≠ urutan tabel di atas secara bebas.** Akun harus selesai sebelum data master: semua halaman CRUD berada di balik autentikasi. Plan 1 tidak menulis satu pun komponen Livewire.

Seeder demo di Plan 1 sengaja besar (1 admin, 10 guru, 7 kelas, 210 siswa, 8 mapel, 105 jadwal) supaya rekap di Plan 5 punya isi realistis. Jumlah itu boleh dikecilkan di `.env` lokal, tapi test Plan 1 mengunci angkanya.

## 10. Di luar lingkup

Tidak dikerjakan di v1 (masih di `PRD` §4.2 dan §9 sebagai rekomendasi):

- Portal login orang tua
- Notifikasi otomatis (WA/email/SMS)
- QR code / face recognition
- Multi-sekolah / multi-tenant
- Aplikasi mobile native

## 11. Hubungan dengan dokumen lain

Spec ini menggantikan beberapa bagian dari dokumen lama. Semua baris di bawah **sudah dieksekusi** pada 2026-09-26 — kolom "Baris" mengacu pada posisi di dokumen lama *sebelum* diperbarui.

| Dokumen | Baris | Aksi | Status |
|---|---|---|---|
| `docs/CLAUDE.MD` | — | **Pindahkan ke root project** agar terbaca otomatis oleh coding agent | Selesai — sekarang `CLAUDE.MD` di root |
| `docs/CLAUDE.MD` | 29 | Salah. Migration **belum** ada. Tulisan ini membuat agent mengira tabel sudah ada dan tidak membuat migration sama sekali | Selesai |
| `docs/CLAUDE.MD` | 44 | "cukup `WHERE date BETWEEN`" belum cukup — perlu validasi rentang tahun ajaran (§4) | Selesai |
| `docs/CLAUDE.MD` | 11 | Tambahkan library PDF; `Laravel Excel` tidak bisa export PDF | Selesai |
| `docs/CLAUDE.MD` | 15 | Tambahkan aturan login via NUPTK dan registrasi dimatikan | Selesai |
| `docs/PRD` | 20 | Kontradiksi. "tidak ada peran/akun terpisah untuk admin" bertentangan dengan tabel peran di baris 16 dan dengan `CLAUDE.md`. Jalur login dipecah lewat kolom `role` pada `users` yang sama | Selesai |
| `docs/PRD` | 30 | Scope menyebut export PDF, tech stack tidak menyebut library-nya | Selesai |
| `docs/PRD` | 115 | "Setup hosting 0.5 hari" tidak realistis untuk cPanel pertama kali; estimasi 1-1.5 hari | Selesai |
| `docs/PRD` | 116 | Testing bukan hanya manual — lihat §8 | Selesai |
| `docs/erd.MD` | 35, 47 | Kolom `day`, `role`, dan `status` diubah dari string ke enum/tinyint; entitas `USERS` belum punya `password` dan `is_active` | Selesai |
| `docs/erd.MD` | 24-25 | `HOMEROOM_TEACHER_ID` harus nullable | Selesai |
| `docs/erd.MD` | seluruh | Tambah entitas `ACADEMIC_YEARS` dan kolom soft delete | Selesai |

## 12. Risiko

| ID | Risiko | Mitigasi |
|---|---|---|
| **R1** | PHP di hosting < 8.2 → tidak ada Laravel yang bisa jalan | Gate di Fase 0, sebelum koding. Fallback: pindah VPS (~Rp 50-150rb/bulan) |
| **R2** | `storage:link` diblokir shared hosting | Fallback disk ke direktori nyata di bawah `public/` (§7) |
| **R3** | Batas jumlah file di hosting terlampaui | `composer install --no-dev`; verifikasi sebelum upload pertama |
| **R4** | Limit upload PHP terlalu kecil untuk import Excel | Verifikasi `upload_max_filesize` / `post_max_size` di gate |

Dua risiko yang sempat muncul sudah tersingkar lewat keputusan manusia: admin sekolah memang punya NUPTK ([D6](#keputusan-desain)), dan sekolah hanya beroperasi Senin-Jumat ([D14](#keputusan-desain)).

Tidak ada risiko migrasi data: proyek baru, tidak ada data absensi lama yang perlu direkonsiliasi.

## 13. Yang berubah dari scope PRD

Empat fitur yang masuk v1 di luar PRD §4.1 ([D11](#keputusan-desain)):

1. **"Tandai Semua Hadir"** — 30 siswa dicentang satu-satu adalah beban yang tidak perlu
2. **Import Excel siswa** — 200+ siswa diinput manual adalah pendorong biaya utama. PRD hanya mengalokasikan 1 hari untuk CRUD siswa
3. **Batas window edit absensi** — [D5](#keputusan-desain)
4. **Riwayat per siswa (drill-down)** — dari matrix wali kelas ke riwayat detail satu siswa
