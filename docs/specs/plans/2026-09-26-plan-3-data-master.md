# Plan 3 — Data Master

> **Untuk agent yang mengerjakan:** WAJIB memakai skill `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan plan ini task per task. Format `- [ ]` dipakai untuk melacak progres.

**Goal:** Enam layar CRUD data master milik admin — tahun ajaran, mata pelajaran, kelas, jadwal pelajaran, siswa, dan impor Excel siswa — dengan seluruh invarian data dijaga di Policy dan service, bukan di view.

**Architecture:** Tujuh komponen Livewire full-page, masing-masing satu fitur, mengikuti konvensi `CLAUDE.md`. Setiap penulisan database lewat service di `app/Services/` supaya bisa diuji tanpa browser dan tidak bisa melewati Policy; komponen hanya memvalidasi (`rules()`) lalu memanggil service. Policy data master diuji lebih dulu di Task 0 sebelum ada satu pun komponen.

**Spec:** `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`

---

## Global Constraints

Nilai-nilai ini berasal dari spec §3, §4, §7 dan Plan 1, lalu berlaku untuk **setiap task**. Jangan menyimpang tanpa keputusan manusia.

- **Gate versi sudah diputuskan di Plan 1 Task 1: PHP ≥ 8.3, Laravel `^13.0`.** Tidak ada fallback (D16). Pakai apa adanya; jangan menaikkan atau menurunkan constraint Composer di plan ini.

- **`APP_TIMEZONE=Asia/Jakarta` wajib di `.env`.** Plan 1 sudah menyetelnya. Default datefield `type="date"` di form memakai tanggal lokal, jadi `today()` dan rentang tahun ajaran tidak bergeser.
- **`QUEUE_CONNECTION=sync`.** Tidak ada `queue:work` di shared hosting, dan tidak ada `ShouldQueue` di plan ini. Impor 200+ siswa berjalan di request biasa.
- **Primary key UUID** di semua tabel (trait `HasUuids`). Impor masiva memakai `Str::uuid()` eksplisit karena `insert()` melewati event model.
- **Wali kelas bukan role.** Status wali kelas dibaca lewat `SchoolClass::homeroom_teacher_id`. Tidak ada kolom role baru, tidak ada role `wali`.
- **Scoping hanya di Policy dan Query Object.** Dilarang keras menulis kondisi role di view/component. `Gate::authorize` di `render()` dan di setiap aksi mutasi adalah satu-satunya pintu masuk.
- **Soft delete** di `users`, `students`, `classes`, `subjects`, `schedules`. **Tidak** di `attendances` dan `academic_years`. Tidak ada satu pun jalur hard delete di plan ini.
- **Unique constraint tidak boleh di-bypass.** `classes` unique (`academic_year_id`, `name`); `students.nisn` unique; `users.nuptk` unique. Validasi `Rule::unique` boleh Added di depan, tapi index DB tetap jaring pengaman terakhir dan tidak boleh dilewati dengan `insert`/`upsert`.
- **Test memakai SQLite in-memory** lewat `phpunit.xml` (Plan 1 Task 1). `.env` tidak pernah disentuh test.
- **Semua pesan, label, validasi, dan flash message dalam bahasa Indonesia.** Nama method dan property tetap bahasa Inggris agar konsisten dengan Plan 1.
- **Layout: `#[Layout('layouts.app')]`** — `resources/views/layouts/app.blade.php` itu **view**, bukan Blade component, jadi tag `<x-layouts.app>` tidak akan resolve (dibuktikan di Plan 2 Task 5; ada test yang gagal karena itu). Plan ini tidak membuat layout sendiri, tidak menulis `@extends`, dan tidak mengarang `@section('title')` atau `<x-slot name="title">`. Judul halaman lewat `->title('Tahun Ajaran')` dari method `mount()`/`render()`, memakai `#[Layout('layouts.app')]` di class component.
- **`maatwebsite/excel` dan `config/excel.php` sudah terpasang** di Plan 1 Task 8. Plan ini tidak menambah dependency dan tidak mem-publish ulang config. Disk temp untuk unggahan memakai disk bawaan Laravel; tidak ada file konfigurasi disk baru di plan ini.
- **Batas ukuran impor 5 MB.** Sudah diverifikasi di gate cPanel (spec R4, `upload_max_filesize` / `post_max_size`). Kalau file lebih besar, upload ditolak dengan pesan, bukan timeout.
- **Test 403 harus pakai HTTP request asli, bukan `Livewire::test()`.** `Livewire\Features\SupportTesting\InitialRender` memanggil `temporarilyDisableExceptionHandlingAndMiddleware()` (`InitialRender.php:32`), jadi `AuthorizationException` dari `mount()`/`render()` **tidak pernah sampai ke PHPUnit** — test selalu hijau padahal halamannya 403. Pola yang benar, sudah dipakai Plan 2:

  ```php
  $this->actingAs($guru)->get('/master/tahun-ajaran')->assertForbidden();
  ```

  Pola `$this->expectException(AuthorizationException::class); Livewire::actingAs($guru)->test(X::class);` ada di Task 1-6 versi lama plan ini dan **salah semua**. Test perilaku component (`->call('save')`) tetap pakai `Livewire::test()`.

- **Assertion tanggal jangan lewat `assertDatabaseHas`.** Kolomnya `$table->date()`, tapi cast `date` di model menulis `Y-m-d H:i:s`. MySQL memotongnya jadi `2025-07-01`; SQLite menyimpan `2025-07-01 00:00:00`. Assertion mentah yang benar di produksi jadi salah di test. Ambil lewat model lalu bandingkan `->toDateString()`:

  ```php
  $tahun = AcademicYear::sole();
  $this->assertSame('2025-07-01', $tahun->start_date->toDateString());
  ```

- **`routes/master.php` dan link nav ditambahkan bertahap per task,** satu route per component yang benar-benar sudah ada. Mendaftarkan keenam route di Task 1 berarti lima halaman 500 sampai Task 5-6 selesai. Keadaan akhir tetap enam route.

- **Semua snippet adalah kode final.** Tidak ada kode yang disengaja belum lengkap. Kalau test gagal, perbaiki penyebabnya — jangan menyalin versi yang salah lalu menggantinya.

---

## File Structure

Semua file yang dibuat atau diubah plan ini:

```
app/
  Imports/
    StudentSheetReader.php          # adapter ToCollection + WithHeadingRow
    StudentRowValidator.php         # validasi per baris -> array pratinjau
  Livewire/
    AcademicYearManagement.php
    SubjectManagement.php
    ClassManagement.php
    ScheduleManagement.php
    StudentManagement.php
    StudentImport.php
  Policies/
    AcademicYearPolicy.php          # BARU: viewAny, create, update
    SubjectPolicy.php               # BARU: viewAny, create, update, delete
    SchedulePolicy.php              # MODIFIKASI: + viewAny, create, update, delete
    SchoolClassPolicy.php           # MODIFIKASI: + viewAny, create, update, delete
  Services/
    AcademicYearManager.php         # 1 transaksi: satu tahun aktif + rentang tidak bertindih
    SubjectManager.php
    ClassManager.php                # 1 transaksi: satu wali kelas per tahun ajaran
    ScheduleManager.php             # memanggil ScheduleConflictDetector
    StudentManager.php
    StudentImporter.php             # bulk insert per chunk di dalam 1 transaksi
resources/views/
  components/nav/
    data-master.blade.php           # sub-grup "Data Master", disaring NavLink::visibleTo()
  livewire/
    academic-year-management.blade.php
    subject-management.blade.php
    class-management.blade.php
    schedule-management.blade.php
    student-management.blade.php
    student-import.blade.php
routes/
  master.php                        # 6 route data master, di-require dari dalam grup auth Plan 2
tests/
  Support/
    StudentRowsSheet.php            # helper test: menulis .xlsx asli
  Feature/
    Policy/
      DataMasterPolicyTest.php
    Livewire/
      AcademicYearManagementTest.php
      SubjectManagementTest.php
      ClassManagementTest.php
      ScheduleManagementTest.php
      StudentManagementTest.php
      StudentImportTest.php
```

**Kelas yang dipakai apa adanya dari Plan 1 dan Plan 2** (tidak didefinisikan ulang di plan ini): `AcademicYear`, `SchoolClass`, `Subject`, `Schedule`, `Student`, `User`, `Attendance`; enum `AcademicPeriod`, `DayOfWeek`, `StudentGender`, `UserRole`; `ScheduleConflictDetector`; `AttendanceRecorder`; seluruh factory; `StudentPolicy`; `config/excel.php`.

---

## Task 0: Policy data master

**Files:**
- Create: `app/Policies/AcademicYearPolicy.php`
- Create: `app/Policies/SubjectPolicy.php`
- Modify: `app/Policies/SchoolClassPolicy.php`
- Modify: `app/Policies/SchedulePolicy.php`
- Test: `tests/Feature/Policy/DataMasterPolicyTest.php`

**Consumes:** model + policy Plan 1 Task 3-4
**Produces:** ability `viewAny`/`create`/`update`/`delete` untuk empat entitas data master. Semua screen di plan ini memanggil ability ini, jadi **tidak ada komponen yang ditulis sebelum test ini lulus** (CLAUDE.md: "policy diuji dulu, baru component").

Task ini sengaja diletakkan di depan. `SchoolClassPolicy` dan `SchedulePolicy` Plan 1 hanya punya ability rekap dan absensi; tanpa tambahan di sini, keenam komponen akan ditulis tanpa satu pun sumber kebenaran otorisasi.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Policy/DataMasterPolicyTest.php`:

```php
<?php

namespace Tests\Feature\Policy;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Policies\AcademicYearPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\SubjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DataMasterPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_boleh_kelola_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $policy = app(AcademicYearPolicy::class);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $tahun));
    }

    public function test_admin_boleh_kelola_mapel_kelas_dan_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create();

        $mataPelajaran = app(SubjectPolicy::class);
        $kelasPolicy = app(SchoolClassPolicy::class);
        $jadwalPolicy = app(SchedulePolicy::class);

        $this->assertTrue($mataPelajaran->viewAny($admin));
        $this->assertTrue($mataPelajaran->create($admin));
        $this->assertTrue($mataPelajaran->update($admin, $mapel));
        $this->assertTrue($mataPelajaran->delete($admin, $mapel));

        $this->assertTrue($kelasPolicy->viewAny($admin));
        $this->assertTrue($kelasPolicy->create($admin));
        $this->assertTrue($kelasPolicy->update($admin, $kelas));
        $this->assertTrue($kelasPolicy->delete($admin, $kelas));

        $this->assertTrue($jadwalPolicy->create($admin));
        $this->assertTrue($jadwalPolicy->update($admin, $jadwal));
        $this->assertTrue($jadwalPolicy->delete($admin, $jadwal));
    }

    public function test_guru_tidak_boleh_kelola_tahun_ajaran_dan_mapel(): void
    {
        $guru = User::factory()->guru()->create();
        $tahun = AcademicYear::factory()->create();
        $mapel = Subject::factory()->create();

        $tahunAjaran = app(AcademicYearPolicy::class);
        $mataPelajaran = app(SubjectPolicy::class);

        $this->assertFalse($tahunAjaran->viewAny($guru));
        $this->assertFalse($tahunAjaran->create($guru));
        $this->assertFalse($tahunAjaran->update($guru, $tahun));

        $this->assertFalse($mataPelajaran->viewAny($guru));
        $this->assertFalse($mataPelajaran->create($guru));
        $this->assertFalse($mataPelajaran->update($guru, $mapel));
        $this->assertFalse($mataPelajaran->delete($guru, $mapel));
    }

    public function test_guru_tidak_boleh_kelola_kelas_dan_jadwal(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create();

        $kelasPolicy = app(SchoolClassPolicy::class);
        $jadwalPolicy = app(SchedulePolicy::class);

        $this->assertFalse($kelasPolicy->viewAny($guru));
        $this->assertFalse($kelasPolicy->create($guru));
        $this->assertFalse($kelasPolicy->update($guru, $kelas));
        $this->assertFalse($kelasPolicy->delete($guru, $kelas));

        $this->assertFalse($jadwalPolicy->create($guru));
        $this->assertFalse($jadwalPolicy->update($guru, $jadwal));
        $this->assertFalse($jadwalPolicy->delete($guru, $jadwal));
    }

    public function test_daftar_jadwal_bisa_dilihat_semua_user(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwalOrangLain = Schedule::factory()->create();

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->viewAny($guru));
        $this->assertFalse($policy->view($guru, $jadwalOrangLain));
    }

    public function test_keempat_policy_data_master_terdaftar_di_gate(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();

        // Plan 1 hanya mendaftarkan UserPolicy secara eksplisit di AppServiceProvider.
        // Empat policy lainandalkan auto-discovery Laravel 11+. Kalau auto-discovery
        // itu mati, test yang memanggil app(Policy::class) tetap hijau padahal keenam
        // komponen akan 403 - jadi resolusi Gate wajib diuji lewat Gate, bukan policy.
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', AcademicYear::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Subject::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', SchoolClass::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Schedule::class));

        $this->assertFalse(Gate::forUser($guru)->allows('viewAny', AcademicYear::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', Subject::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', SchoolClass::class));
        $this->assertFalse(Gate::forUser($guru)->allows('create', Schedule::class));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=DataMasterPolicyTest
```

Expected: FAIL — `Target class [App\Policies\AcademicYearPolicy] does not exist`.

- [ ] **Step 3: Tulis 4 policy**

`app/Policies/AcademicYearPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdmin();
    }
}
```

Tidak ada ability `delete`. `academic_years` tidak punya soft delete (spec §3): tahun ajaran ditutup lewat `is_active = false`, jadi menghapus baris yang sudah dipakai `classes` akan meruskan seluruh kelas tahun itu. Menutup lewat `update` + `setActive()` sudah cukup.

`app/Policies/SubjectPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->isAdmin();
    }
}
```

`app/Policies/SchoolClassPolicy.php` — ganti seluruh isi, `viewRecap` Plan 1 dipertahankan apa adanya:

```php
<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin();
    }

    public function viewRecap(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isAdmin() || $schoolClass->homeroom_teacher_id === $user->id;
    }
}
```

`app/Policies/SchedulePolicy.php` — ganti seluruh isi, `view` dan `recordAttendance` Plan 1 dipertahankan apa adanya:

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Guru;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin();
    }

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

`viewAny` sengaja longgar untuk guru: daftar jadwal milik guru adalah dashboard Plan 4, dan haknya ada di `view` per baris plus scoping query `teacher_id`. `viewAny` yang sempit akan membuat dashboard guru kosong, bukan lebih aman.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=PolicyTest
```

Expected: 26 tests pass (20 test Plan 1 + 6 test baru). Angka 20 sudah diverifikasi dengan `php artisan test --filter=PolicyTest` di branch `feat/plan-3-data-master` sebelum Task 0 dikerjakan.

Kalau test Plan 1 gagal setelah policy dimodifikasi, berarti `viewRecap` atau `recordAttendance` berubah. Keduanya harus persis seperti di Plan 1 — plan ini hanya menambah ability.

---

## Task 1: `AcademicYearManagement`

**Files:**
- Create: `app/Services/AcademicYearManager.php`
- Create: `app/Livewire/AcademicYearManagement.php`
- Create: `resources/views/livewire/academic-year-management.blade.php`
- Test: `tests/Feature/Livewire/AcademicYearManagementTest.php`

**Consumes:** `AcademicYear`, `AcademicPeriod`, factory, `AcademicYearPolicy` (Task 0)
**Produces:** layar CRUD tahun ajaran. Dua invarian dijaga di service: **hanya satu tahun ajaran aktif** dan **rentang tanggal tidak boleh bertindih**.

`academic_years` tidak punya soft delete, jadi layar ini tidak punya tombol hapus. Menutup tahun ajaran = `is_active = false`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/AcademicYearManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\AcademicPeriod;
use App\Livewire\AcademicYearManagement;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $nama, string $mulai, string $selesai, bool $aktif = false): array
    {
        return [
            'name' => $nama,
            'period' => AcademicPeriod::Ganjil->value,
            'start_date' => $mulai,
            'end_date' => $selesai,
            'is_active' => $aktif,
        ];
    }

    public function test_admin_bisa_menambah_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-07-01', '2025-12-31'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_years', [
            'name' => '2025/2026',
            'period' => 'ganjil',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => false,
        ]);
    }

    public function test_validasi_wajib_benar(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('', '', ''))
            ->call('save')
            ->assertHasErrors(['name', 'start_date', 'end_date']);

        $this->assertSame(0, AcademicYear::count());
    }

    public function test_tanggal_selesai_harus_setelah_tanggal_mulai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-12-31', '2025-07-01'))
            ->call('save')
            ->assertHasErrors(['end_date']);

        $this->assertSame(0, AcademicYear::count());
    }

    public function test_rentang_tanggal_bertindih_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        AcademicYear::factory()->create([
            'name' => '2025/2026 Ganjil',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026 Genap', '2025-10-01', '2026-06-30'))
            ->call('save')
            ->assertHasErrors(['start_date']);

        $this->assertSame(1, AcademicYear::count());
    }

    public function test_mengaktifkan_satu_tahun_menonaktifkan_yang_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $lama = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->set($this->form('2025/2026', '2025-07-01', '2026-06-30', true))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
        $this->assertFalse($lama->fresh()->is_active);
    }

    public function test_admin_bisa_mengubah_dan_menonaktifkan_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create([
            'name' => '2025/2026 Ganjil',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AcademicYearManagement::class)
            ->call('editForm', $tahun->id)
            ->set('name', '2025/2026 Ganjil Revisi')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_years', [
            'id' => $tahun->id,
            'name' => '2025/2026 Ganjil Revisi',
            'is_active' => false,
        ]);
    }

    public function test_guru_tidak_bisa_membuka_halaman_tahun_ajaran(): void
    {
        $this->expectException(AuthorizationException::class);

        Livewire::actingAs(User::factory()->guru()->create())
            ->test(AcademicYearManagement::class);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AcademicYearManagementTest
```

Expected: FAIL — `Unable to find component: AcademicYearManagement`.

- [ ] **Step 3: Tulis service**

`app/Services/AcademicYearManager.php`:

```php
<?php

namespace App\Services;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicYearManager
{
    public function save(?AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $mulai = Carbon::parse($data['start_date'])->startOfDay();
            $selesai = Carbon::parse($data['end_date'])->startOfDay();

            $this->tolakRentangBertindih($mulai, $selesai, $academicYear?->id);

            $tahun = $academicYear ?? new AcademicYear;

            $tahun->fill([
                'name' => $data['name'],
                'period' => AcademicPeriod::from($data['period']),
                'start_date' => $mulai->toDateString(),
                'end_date' => $selesai->toDateString(),
            ]);
            $tahun->save();

            if ((bool) $data['is_active']) {
                $this->matikanLainnya($tahun->id);
                $tahun->update(['is_active' => true]);
            }

            return $tahun;
        });
    }

    public function setActive(AcademicYear $academicYear, bool $aktif): void
    {
        DB::transaction(function () use ($academicYear, $aktif): void {
            if ($aktif) {
                $this->matikanLainnya($academicYear->id);
            }

            $academicYear->update(['is_active' => $aktif]);
        });
    }

    private function matikanLainnya(string $kecualiId): void
    {
        AcademicYear::query()
            ->whereKeyNot($kecualiId)
            ->update(['is_active' => false]);
    }

    private function tolakRentangBertindih(Carbon $mulai, Carbon $selesai, ?string $kecualiId): void
    {
        $bertindih = AcademicYear::query()
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->whereDate('start_date', '<=', $selesai->toDateString())
            ->whereDate('end_date', '>=', $mulai->toDateString())
            ->exists();

        if ($bertindih) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang tanggal bertindih dengan tahun ajaran yang sudah ada.',
            ]);
        }
    }
}
```

Dua invarian hidup di sini, bukan di komponen:

1. **`matikanLainnya` di dalam transaksi yang sama** dengan penyimpanan. Kalau `is_active` ditulis di luar transaksi, dua request bersamaan bisa menyisakan dua tahun aktif.
2. **`tolakRentangBertindih` di-overlap inklusi**: 1 Juli sampai 31 Desember dan 1 Oktober sampai 30 Juni dianggap bertindih. Tanpa ini, rekap bulanan Plan 5 punya dua sumber batas dan hasil_monthly bisa menghitung tanggal yang sama dua kali.

- [ ] **Step 4: Tulis komponen**

`app/Livewire/AcademicYearManagement.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use App\Services\AcademicYearManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AcademicYearManagement extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public string $period = 'ganjil';

    public string $start_date = '';

    public string $end_date = '';

    public bool $is_active = false;

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_years', 'name')->ignore($this->editingId),
            ],
            'period' => ['required', Rule::in(array_column(AcademicPeriod::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->period = AcademicPeriod::Ganjil->value;
        $this->start_date = '';
        $this->end_date = '';
        $this->is_active = false;
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $tahun = AcademicYear::findOrFail($id);

        Gate::authorize('update', $tahun);

        $this->editingId = $tahun->id;
        $this->name = $tahun->name;
        $this->period = $tahun->period->value;
        $this->start_date = $tahun->start_date->format('Y-m-d');
        $this->end_date = $tahun->end_date->format('Y-m-d');
        $this->is_active = $tahun->is_active;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $tahun = $this->editingId === null ? null : AcademicYear::findOrFail($this->editingId);

        Gate::authorize($tahun === null ? 'create' : 'update', $tahun ?? AcademicYear::class);

        $baru = $tahun === null;

        app(AcademicYearManager::class)->save($tahun, [
            'name' => $this->name,
            'period' => $this->period,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Tahun ajaran ditambahkan.' : 'Tahun ajaran diperbarui.');
    }

    public function setActive(string $id): void
    {
        $tahun = AcademicYear::findOrFail($id);

        Gate::authorize('update', $tahun);

        $aktif = ! $tahun->is_active;

        app(AcademicYearManager::class)->setActive($tahun, $aktif);

        session()->flash('sukses', $aktif ? 'Tahun ajaran diaktifkan.' : 'Tahun ajaran dinonaktifkan.');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        return view('livewire.academic-year-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'periode' => AcademicPeriod::cases(),
        ]);
    }
}
```

`Gate::authorize` ada di `render()`, bukan hanya di `mount()`. `render()` jalan di setiap request Livewire, jadi halaman tidak bisa dibuka ulang lewat request berikutnya yang melewati `mount()`.

- [ ] **Step 5: Tulis view**

`resources/views/livewire/academic-year-management.blade.php`:

```blade
<x-layouts.app title="Tahun Ajaran">

    <div class="space-y-6">
        <h1 class="text-lg font-semibold text-gray-900">Tahun Ajaran</h1>

        @if (session('sukses'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="font-medium text-gray-900">
                    {{ $editingId === null ? 'Tambah Tahun Ajaran' : 'Ubah Tahun Ajaran' }}
                </h2>

                <label class="block text-sm">
                    <span class="text-gray-700">Nama Tahun Ajaran</span>
                    <input type="text" wire:model="name" placeholder="2025/2026"
                        class="mt-1 w-full rounded border-gray-300" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Periode</span>
                    <select wire:model="period" class="mt-1 w-full rounded border-gray-300">
                        @foreach ($periode as $item)
                            <option value="{{ $item->value }}">{{ ucfirst($item->value) }}</option>
                        @endforeach
                    </select>
                    @error('period') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Mulai</span>
                    <input type="date" wire:model="start_date" class="mt-1 w-full rounded border-gray-300" />
                    @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Selesai</span>
                    <input type="date" wire:model="end_date" class="mt-1 w-full rounded border-gray-300" />
                    @error('end_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="is_active" />
                    Jadikan tahun ajaran aktif
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                    <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
                </div>
            </form>

            <div class="lg:col-span-2 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Nama</th>
                            <th class="px-4 py-2">Periode</th>
                            <th class="px-4 py-2">Rentang</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tahunAjaran as $tahun)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-2">{{ $tahun->name }}</td>
                                <td class="px-4 py-2">{{ ucfirst($tahun->period->value) }}</td>
                                <td class="px-4 py-2">
                                    {{ $tahun->start_date->format('d/m/Y') }} - {{ $tahun->end_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-2">
                                    @if ($tahun->is_active)
                                        <span class="rounded bg-green-100 px-2 py-1 text-xs text-green-800">Aktif</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $tahun->id }}')" class="text-sm text-blue-700">Ubah</button>
                                    <button wire:click="setActive('{{ $tahun->id }}')" class="ml-2 text-sm text-gray-700">
                                        {{ $tahun->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    Belum ada tahun ajaran. Tambahkan satu sebelum membuat kelas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AcademicYearManagementTest
```

**Expected: 7 tests pass**

Kalau `test_mengaktifkan_satu_tahun_menonaktifkan_yang_lain` gagal dengan jumlah tahun aktif 2, `matikanLainnya` dipanggil di luar `DB::transaction`.

- [ ] **Step 7: Daftarkan route dan link menu**

`routes/master.php` — semua route data master ada di sini, bukan disebar ke `routes/web.php`:

```php
<?php

use App\Livewire\AcademicYearManagement;
use App\Livewire\ClassManagement;
use App\Livewire\ScheduleManagement;
use App\Livewire\StudentImport;
use App\Livewire\StudentManagement;
use App\Livewire\SubjectManagement;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('master')->name('master.')->group(function (): void {
    Route::get('tahun-ajaran', AcademicYearManagement::class)->name('tahun-ajaran');
    Route::get('mapel', SubjectManagement::class)->name('mapel');
    Route::get('kelas', ClassManagement::class)->name('kelas');
    Route::get('jadwal', ScheduleManagement::class)->name('jadwal');
    Route::get('siswa', StudentManagement::class)->name('siswa');
    Route::get('siswa/impor', StudentImport::class)->name('siswa.impor');
});
```

Route **tidak** memakai middleware sendiri. Plan 2 Task 4 sudah membuat satu-satunya grup autentikasi di `routes/web.php`:

```php
Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    // ...
});
```

File `routes/master.php` ini di-`require` dari **dalam** grup itu, supaya route data master otomatis mendapat `auth` dan `EnsureUserIsActive` tanpa mengulangi middleware di setiap route. Navigasi ke data master adalah perpindahan halaman dalam aplikasi, bukan masuk dari luar — ia harus melewati middleware yang sama dengan dashboard.

`routes/web.php` — tambahkan dua baris `use` di bagian atas:

```php
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
```

Lalu di dalam grup `Route::middleware(['auth', EnsureUserIsActive::class])->group(...)`, tambahkan satu baris **di paling akhir** grup:

```php
    require __DIR__.'/master.php';
```

`resources/views/components/nav/data-master.blade.php`:

```blade
@php
    use App\Support\NavLink;

    $links = [
        NavLink::adminOnly('Tahun Ajaran', 'master.tahun-ajaran', 'calendar'),
        NavLink::adminOnly('Mata Pelajaran', 'master.mapel', 'book-open'),
        NavLink::adminOnly('Kelas', 'master.kelas', 'academic-cap'),
        NavLink::adminOnly('Jadwal Pelajaran', 'master.jadwal', 'clock'),
        NavLink::adminOnly('Siswa', 'master.siswa', 'users'),
        NavLink::adminOnly('Impor Siswa', 'master.siswa.impor', 'arrow-up-tray'),
    ];

    $visible = array_filter(
        $links,
        fn (NavLink $link) => $link->visibleTo(auth()->user()->isAdmin()),
    );
@endphp

@if ($visible !== [])
    <flux:sidebar.group heading="Data Master" class="grid">
        @foreach ($visible as $link)
            <flux:sidebar.item
                icon="{{ $link->icon }}"
                :href="route($link->routeName)"
                :current="request()->routeIs($link->routeName)"
                wire:navigate
            >
                {{ $link->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endif
```

Semua link memakai `NavLink::adminOnly()`, **bukan** `new NavLink(...)`. `visibleTo()` mengembalikan `true` untuk link yang `adminOnly`-nya `false`, jadi filter `visibleTo(auth()->user()->isAdmin())` hanya menyaring kalau flag-nya benar-benar ditandai. Dengan `new NavLink(...)` semua link terlihat untuk semua peran, dan guru mendapat sub-grup "Data Master" yang seluruhnya mengembalikan 403.

`heading="Data Master"` ditulis sebagai string biasa, bukan `__('Data Master')`. Kalau route `master.*` tidak ada, `route($link->routeName)` sudah lebih dulu melempar `RouteNotFoundException` — memakai helper `__()` di sini hanya menambah satu hal yang bisa gagal tanpa menambah apa pun.

Pola ini sama dengan `x-nav.user-link` Plan 2: `NavLink` dipakai sebagai sumber data, `visibleTo()` menyaring di dalam komponen, dan sidebar tidak perlu `@if` per link.

Sisipkan tag `<x-nav.data-master />` ke dalam `flux:sidebar.nav` di `resources/views/layouts/app/sidebar.blade.php`, tepat di bawah `<x-nav.user-link />` dan **masih di dalam** grup `Sistem Absensi` milik Plan 2:

```blade
            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Sistem Absensi')" class="grid">
                    <x-nav.dashboard-link />

                    <x-nav.user-link />

                    <x-nav.data-master />
                </flux:sidebar.group>
            </flux:sidebar.nav>
```

`<x-nav.data-master />` memakai `flux:sidebar.group` sendiri, jadi ia merender sub-grup di dalam grup `Sistem Absensi` dan tidak memindahkan apa pun keluar dari sana. Urutannya mengikuti urutan pengisian data: tahun ajaran, mapel, kelas, jadwal, siswa.

Verifikasi route terdaftar:

```powershell
php artisan route:list --path=master
```

Expected: 6 baris, semua memakai middleware `auth`.

- [ ] **Step 8: Commit**

```powershell
git add -A
git commit -m "feat: layar tahun ajaran dengan invarian satu tahun aktif dan rentang tanpa tumpang tindih"
```

---

---

## Task 2: `SubjectManagement`

**Files:**
- Create: `app/Services/SubjectManager.php`
- Create: `app/Livewire/SubjectManagement.php`
- Create: `resources/views/livewire/subject-management.blade.php`
- Test: `tests/Feature/Livewire/SubjectManagementTest.php`

**Consumes:** `Subject`, `SubjectPolicy` (Task 0)
**Produces:** CRUD mapel + arsip (soft delete) dan pemulihan.

Kolom `subjects.code` nullable di DB, tapi form mewajibkannya dan maksimal 10 karakter. Kode dipakai admin sebagai label cepat saat memilih mapel di form jadwal; mapel tanpa kode memaksa admin menebak dari nama panjang. Kolom `name` tidak punya unique index di DB, jadi keunakannya dijaga `Rule::unique` di level form.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/SubjectManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SubjectManagement;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_mapel(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'name' => 'Matematika']);
    }

    public function test_kode_mapel_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK')
            ->set('name', 'Matematika Dasar')
            ->call('save')
            ->assertHasErrors(['code']);

        $this->assertSame(1, Subject::count());
    }

    public function test_nama_mapel_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK2')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(1, Subject::count());
    }

    public function test_mapel_diarsipkan_tidak_muncul_di_daftar_aktif(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->assertSee('Matematika')
            ->call('arsipkan', $mapel->id)
            ->assertDontSee('Matematika');

        $this->assertSoftDeleted('subjects', ['id' => $mapel->id]);
    }

    public function test_mapel_diarsipkan_tidak_menghapus_absensi_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;

        Livewire::actingAs($admin)->test(SubjectManagement::class)->call('arsipkan', $mapel->id);

        $this->assertSoftDeleted('subjects', ['id' => $mapel->id]);
        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('subjects', ['id' => $mapel->id]);
    }

    public function test_mapel_arsip_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        $mapel->delete();

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('showArchived', true)
            ->call('pulihkan', $mapel->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('subjects', ['id' => $mapel->id]);
    }

    public function test_guru_tidak_bisa_membuka_halaman_mapel(): void
    {
        $this->expectException(AuthorizationException::class);

        Livewire::actingAs(User::factory()->guru()->create())
            ->test(SubjectManagement::class);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=SubjectManagementTest
```

Expected: FAIL — `Unable to find component: SubjectManagement`.

- [ ] **Step 3: Tulis service, komponen, dan view**

`app/Services/SubjectManager.php`:

```php
<?php

namespace App\Services;

use App\Models\Subject;

final class SubjectManager
{
    public function save(?Subject $subject, array $data): Subject
    {
        $mapel = $subject ?? new Subject;

        $mapel->fill([
            'code' => $data['code'],
            'name' => $data['name'],
        ]);
        $mapel->save();

        return $mapel;
    }

    public function arsipkan(Subject $subject): void
    {
        $subject->delete();
    }

    public function pulihkan(Subject $subject): void
    {
        $subject->restore();
    }
}
```

`app/Livewire/SubjectManagement.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Services\SubjectManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SubjectManagement extends Component
{
    public ?string $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $showArchived = false;

    protected function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('subjects', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
        ];
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $mapel = Subject::withTrashed()->findOrFail($id);

        Gate::authorize('update', $mapel);

        $this->editingId = $mapel->id;
        $this->code = (string) $mapel->code;
        $this->name = $mapel->name;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $mapel = $this->editingId === null ? null : Subject::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($mapel === null ? 'create' : 'update', $mapel ?? Subject::class);

        $baru = $mapel === null;

        app(SubjectManager::class)->save($mapel, [
            'code' => $this->code,
            'name' => $this->name,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Mata pelajaran ditambahkan.' : 'Mata pelajaran diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $mapel = Subject::findOrFail($id);

        Gate::authorize('delete', $mapel);

        app(SubjectManager::class)->arsipkan($mapel);

        session()->flash('sukses', 'Mata pelajaran diarsipkan. Jadwal dan absensi lamanya tetap utuh.');
    }

    public function pulihkan(string $id): void
    {
        $mapel = Subject::withTrashed()->findOrFail($id);

        Gate::authorize('update', $mapel);

        app(SubjectManager::class)->pulihkan($mapel);

        session()->flash('sukses', 'Mata pelajaran dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Subject::class);

        return view('livewire.subject-management', [
            'daftar' => Subject::query()
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->when(! $this->showArchived, fn ($query) => $query->orderBy('name'))
                ->get(),
        ]);
    }
}
```

`->whereNull('deleted_at')` pada `Rule::unique` disengaja: mapel yang diarsipkan tidak lagi ikut dihitung uniqueness nama dan kodenya, jadi admin boleh membuat mapel dengan nama sama tanpa harus memulihkan yang lama lebih dulu. Kolom `subjects` tidak punya unique index di DB, jadi tidak ada bentrok dengan index.

`resources/views/livewire/subject-management.blade.php`:

```blade
<x-layouts.app title="Mata Pelajaran">

    <div class="space-y-6">
        <h1 class="text-lg font-semibold text-gray-900">Mata Pelajaran</h1>

        @if (session('sukses'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="font-medium text-gray-900">
                    {{ $editingId === null ? 'Tambah Mata Pelajaran' : 'Ubah Mata Pelajaran' }}
                </h2>

                <label class="block text-sm">
                    <span class="text-gray-700">Kode</span>
                    <input type="text" wire:model="code" maxlength="10" placeholder="MTK"
                        class="mt-1 w-full rounded border-gray-300" />
                    @error('code') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Nama Mata Pelajaran</span>
                    <input type="text" wire:model="name"
                        class="mt-1 w-full rounded border-gray-300" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                    <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
                </div>
            </form>

            <div class="lg:col-span-2 space-y-3">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:click="toggleArsip" />
                    Tampilkan yang diarsipkan
                </label>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Kode</th>
                                <th class="px-4 py-2">Nama</th>
                                <th class="px-4 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $mapel)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2">{{ $mapel->code }}</td>
                                    <td class="px-4 py-2">
                                        {{ $mapel->name }}
                                        @if ($mapel->trashed())
                                            <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Diarsipkan</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <button wire:click="editForm('{{ $mapel->id }}')" class="text-sm text-blue-700">Ubah</button>
                                        @if ($mapel->trashed())
                                            <button wire:click="pulihkan('{{ $mapel->id }}')" class="ml-2 text-sm text-green-700">Pulihkan</button>
                                        @else
                                            <button wire:click="arsipkan('{{ $mapel->id }}')" class="ml-2 text-sm text-red-700">Arsipkan</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                                        Belum ada mata pelajaran.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=SubjectManagementTest
```

**Expected: 7 tests pass**

Kalau `test_mapel_diarsipkan_tidak_muncul_di_daftar_aktif` gagal, `render()` tidak memakai global scope soft delete — pastikan `Subject::query()` tanpa `withTrashed()` untuk daftar aktif.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: CRUD mata pelajaran dengan arsip dan pemulihan"
```

---

---

## Task 3: `ClassManagement`

**Files:**
- Create: `app/Services/ClassManager.php`
- Create: `app/Livewire/ClassManagement.php`
- Create: `resources/views/livewire/class-management.blade.php`
- Test: `tests/Feature/Livewire/ClassManagementTest.php`

**Consumes:** `SchoolClass`, `AcademicYear`, `User`, `Student`, `Schedule`, `Attendance`, `SchoolClassPolicy` (Task 0)
**Produces:** CRUD kelas + penugasan wali kelas, plus arsip (soft delete) dengan peringatan jumlah siswa.

Empat hal yang mudah salah di layar ini:

1. **`academic_year_id` wajib.** Kelas dibuat ulang tiap tahun ajaran (D2). Kelas yang sama di tahun berbeda = baris berbeda, jadi tahun ajaran bukan filter opsional melainkan identitas kelas.
2. **`homeroom_teacher_id` nullable.** Kelas boleh dibuat sebelum wali ditunjuk. Layar harus menampilkan empty state, bukan `{null}`.
3. **Unique `('academic_year_id', 'name')` ada di DB** (Plan 1 Task 2). Index itu **tidak** menyertakan `deleted_at`, jadi kelas yang diarsipkan tetap memblokir nama yang sama di tahun ajaran itu. Menghapus baris soft-deleted demi "membebaskan nama" adalah bypass unique constraint — jangan dilakukan. Nama kelas di tahun ajaran yang sama memang tidak boleh dipakai ulang.
4. **Tidak ada jalur hard delete.** Kelas yang punya absensi hanya boleh diarsipkan; absensinya menunjuk `schedules`, dan menghapus baris kelas akan meruskan seluruh riwayat.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/ClassManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ClassManagement;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_kelas_dengan_wali_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $wali = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', $wali->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('classes', [
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
            'grade_level' => 7,
            'homeroom_teacher_id' => $wali->id,
        ]);
    }

    public function test_wali_kelas_bisa_kosong(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-B')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $kelas = SchoolClass::where('name', 'VII-B')->firstOrFail();

        $this->assertNull($kelas->homeroom_teacher_id);
        $this->assertTrue(SchoolClass::withoutGlobalScopes()->find($kelas->id)->exists());
    }

    public function test_nama_kelas_duplikat_di_tahun_ajaran_yang_sama_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(1, SchoolClass::count());
    }

    public function test_nama_kelas_sama_boleh_di_tahun_ajaran_berbeda(): void
    {
        $admin = User::factory()->admin()->create();
        $tahunIni = AcademicYear::factory()->create();
        $tahunLalu = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);
        SchoolClass::factory()->create(['academic_year_id' => $tahunIni->id, 'name' => 'VII-A']);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahunLalu->id)
            ->set('name', 'VII-A')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, SchoolClass::where('name', 'VII-A')->count());
    }

    public function test_satu_guru_hanya_bisa_menjadi_wali_satu_kelas_per_tahun(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $wali = User::factory()->guru()->create();
        SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $wali->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'VII-B')
            ->set('grade_level', 7)
            ->set('homeroom_teacher_id', $wali->id)
            ->call('save')
            ->assertHasErrors(['homeroom_teacher_id']);

        $this->assertSame(1, SchoolClass::count());
    }

    public function test_kelas_berisi_absensi_hanya_diarsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)->test(ClassManagement::class)->call('arsipkan', $kelas->id);

        $this->assertSoftDeleted('classes', ['id' => $kelas->id]);
        $this->assertSame(0, SchoolClass::count());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_arsipkan_kelas_peringatkan_jumlah_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        Student::factory()->count(3)->create(['class_id' => $kelas->id]);

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->call('arsipkan', $kelas->id)
            ->assertSee('3 siswa');

        $this->assertSoftDeleted('classes', ['id' => $kelas->id]);
    }

    public function test_tingkat_kelas_hanya_tujuh_sampai_sembilan(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClassManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('name', 'X-A')
            ->set('grade_level', 10)
            ->set('homeroom_teacher_id', '')
            ->call('save')
            ->assertHasErrors(['grade_level']);

        $this->assertSame(0, SchoolClass::count());
    }

    public function test_guru_tidak_bisa_membuka_halaman_kelas(): void
    {
        $this->expectException(AuthorizationException::class);

        Livewire::actingAs(User::factory()->guru()->create())
            ->test(ClassManagement::class);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=ClassManagementTest
```

Expected: FAIL — `Unable to find component: ClassManagement`.

- [ ] **Step 3: Tulis service, komponen, dan view**

`app/Services/ClassManager.php`:

```php
<?php

namespace App\Services;

use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassManager
{
    public function save(?SchoolClass $schoolClass, array $data): SchoolClass
    {
        return DB::transaction(function () use ($schoolClass, $data) {
            $waliId = $data['homeroom_teacher_id'] === '' || $data['homeroom_teacher_id'] === null
                ? null
                : (string) $data['homeroom_teacher_id'];

            if ($waliId !== null) {
                $this->tolakWaliGanda($waliId, (string) $data['academic_year_id'], $schoolClass?->id);
            }

            $kelas = $schoolClass ?? new SchoolClass;

            $kelas->fill([
                'academic_year_id' => $data['academic_year_id'],
                'name' => $data['name'],
                'grade_level' => (int) $data['grade_level'],
                'homeroom_teacher_id' => $waliId,
            ]);
            $kelas->save();

            return $kelas;
        });
    }

    /**
     * Mengarsipkan kelas dan mengembalikan jumlah siswa yang menempel,
     * supaya komponen bisa memperingatkan tanpa query kedua.
     */
    public function arsipkan(SchoolClass $schoolClass): int
    {
        return (int) DB::transaction(function () use ($schoolClass) {
            $jumlahSiswa = $schoolClass->students()->count();
            $schoolClass->delete();

            return $jumlahSiswa;
        });
    }

    public function pulihkan(SchoolClass $schoolClass): void
    {
        $schoolClass->restore();
    }

    private function tolakWaliGanda(string $waliId, string $academicYearId, ?string $kecualiId): void
    {
        $sudah = SchoolClass::query()
            ->where('homeroom_teacher_id', $waliId)
            ->where('academic_year_id', $academicYearId)
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->exists();

        if ($sudah) {
            throw ValidationException::withMessages([
                'homeroom_teacher_id' => 'Guru ini sudah menjadi wali kelas pada tahun ajaran tersebut.',
            ]);
        }
    }
}
```

`arsipkan()` tidak punya padanan hard delete. Soft delete adalah satu-satunya jalur, jadi `attendances` yang menunjuk `schedules` kelas itu tetap utuh dan bisa dipulihkan kalau kelas ternyata salah diarsipkan.

`app/Livewire/ClassManagement.php`:

```php
<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClassManagement extends Component
{
    public ?string $editingId = null;

    public string $academic_year_id = '';

    public string $name = '';

    public string $grade_level = '7';

    public string $homeroom_teacher_id = '';

    public bool $showArchived = false;

    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:20',
                Rule::unique('classes', 'name')
                    ->where('academic_year_id', $this->academic_year_id)
                    ->ignore($this->editingId),
            ],
            'grade_level' => ['required', 'in:7,8,9'],
            'homeroom_teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('role', 'guru')
                        ->whereNull('deleted_at')),
            ],
        ];
    }

    public function mount(): void
    {
        if ($this->academic_year_id === '') {
            $aktif = AcademicYear::query()->where('is_active', true)->first();

            $this->academic_year_id = $aktif?->id ?? (string) AcademicYear::query()->orderByDesc('start_date')->value('id');
        }
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->grade_level = '7';
        $this->homeroom_teacher_id = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $kelas = SchoolClass::withTrashed()->findOrFail($id);

        Gate::authorize('update', $kelas);

        $this->editingId = $kelas->id;
        $this->academic_year_id = $kelas->academic_year_id;
        $this->name = $kelas->name;
        $this->grade_level = (string) $kelas->grade_level;
        $this->homeroom_teacher_id = (string) $kelas->homeroom_teacher_id;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $kelas = $this->editingId === null ? null : SchoolClass::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($kelas === null ? 'create' : 'update', $kelas ?? SchoolClass::class);

        $baru = $kelas === null;

        app(ClassManager::class)->save($kelas, [
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'grade_level' => $this->grade_level,
            'homeroom_teacher_id' => $this->homeroom_teacher_id,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Kelas ditambahkan.' : 'Kelas diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $kelas = SchoolClass::findOrFail($id);

        Gate::authorize('delete', $kelas);

        $jumlahSiswa = app(ClassManager::class)->arsipkan($kelas);

        session()->flash(
            'sukses',
            $jumlahSiswa > 0
                ? "Kelas {$kelas->name} diarsipkan. {$jumlahSiswa} siswa dan riwayat absensinya tetap tersimpan."
                : "Kelas {$kelas->name} diarsipkan."
        );
    }

    public function pulihkan(string $id): void
    {
        $kelas = SchoolClass::withTrashed()->findOrFail($id);

        Gate::authorize('update', $kelas);

        app(ClassManager::class)->pulihkan($kelas);

        session()->flash('sukses', 'Kelas dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', SchoolClass::class);

        return view('livewire.class-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'waliTersedia' => User::query()
                ->where('role', 'guru')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'daftar' => SchoolClass::query()
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->with('academicYear')
                ->with('homeroomTeacher')
                ->withCount('students')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
```

`grade_level` dikirim sebagai string dari `<select>`, dan `SchoolClass::booted` (Plan 1 Task 3) melempar `ValidationException` kalau nilainya bukan 7/8/9. Rule `in:7,8,9` di `rules()` menahan lebih dulu supaya pesannya muncul di bawah field, bukan sebagai exception yang tidak tertangkap.

`resources/views/livewire/class-management.blade.php`:

```blade
<x-layouts.app title="Kelas">

    <div class="space-y-6">
        <h1 class="text-lg font-semibold text-gray-900">Kelas</h1>

        @if (session('sukses'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="font-medium text-gray-900">
                    {{ $editingId === null ? 'Tambah Kelas' : 'Ubah Kelas' }}
                </h2>

                <label class="block text-sm">
                    <span class="text-gray-700">Tahun Ajaran</span>
                    <select wire:model="academic_year_id" class="mt-1 w-full rounded border-gray-300">
                        @foreach ($tahunAjaran as $tahun)
                            <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                        @endforeach
                    </select>
                    @error('academic_year_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Nama Kelas</span>
                    <input type="text" wire:model="name" placeholder="VII-A"
                        class="mt-1 w-full rounded border-gray-300" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Tingkat</span>
                    <select wire:model="grade_level" class="mt-1 w-full rounded border-gray-300">
                        @foreach ([7, 8, 9] as $tingkat)
                            <option value="{{ $tingkat }}">Kelas {{ $tingkat }}</option>
                        @endforeach
                    </select>
                    @error('grade_level') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Wali Kelas</span>
                    <select wire:model="homeroom_teacher_id" class="mt-1 w-full rounded border-gray-300">
                        <option value="">Belum ditentukan</option>
                        @foreach ($waliTersedia as $wali)
                            <option value="{{ $wali->id }}">{{ $wali->name }}</option>
                        @endforeach
                    </select>
                    @error('homeroom_teacher_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                    <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
                </div>
            </form>

            <div class="lg:col-span-2 space-y-3">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:click="toggleArsip" />
                    Tampilkan yang diarsipkan
                </label>

                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Kelas</th>
                                <th class="px-4 py-2">Tahun Ajaran</th>
                                <th class="px-4 py-2">Wali Kelas</th>
                                <th class="px-4 py-2">Siswa</th>
                                <th class="px-4 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daftar as $kelas)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2">
                                        {{ $kelas->name }}
                                        @if ($kelas->trashed())
                                            <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Diarsipkan</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">{{ $kelas->academicYear?->name }}</td>
                                    <td class="px-4 py-2">
                                        {{ $kelas->homeroomTeacher?->name ?? 'Belum ditentukan' }}
                                    </td>
                                    <td class="px-4 py-2">{{ $kelas->students_count }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <button wire:click="editForm('{{ $kelas->id }}')" class="text-sm text-blue-700">Ubah</button>
                                        @if ($kelas->trashed())
                                            <button wire:click="pulihkan('{{ $kelas->id }}')" class="ml-2 text-sm text-green-700">Pulihkan</button>
                                        @else
                                            <button wire:click="arsipkan('{{ $kelas->id }}')" class="ml-2 text-sm text-red-700">Arsipkan</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                        Belum ada kelas pada tahun ajaran ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=ClassManagementTest
```

**Expected: 9 tests pass**

Kalau `test_kelas_berisi_absensi_hanya_diarsipkan` gagal dengan `SchoolClass::count()` masih 1, soft delete tidak dipakai — `arsipkan()` harus `$schoolClass->delete()`, bukan `forceDelete()`.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: CRUD kelas per tahun ajaran dengan wali kelas tunggal dan arsip"
```

---

---

## Task 4: `ScheduleManagement`

**Files:**
- Create: `app/Services/ScheduleManager.php`
- Create: `app/Livewire/ScheduleManagement.php`
- Create: `resources/views/livewire/schedule-management.blade.php`
- Test: `tests/Feature/Livewire/ScheduleManagementTest.php`

**Consumes:** `Schedule`, `SchoolClass`, `Subject`, `User`, `DayOfWeek`, `ScheduleConflictDetector` (Plan 1 Task 6), `SchedulePolicy` (Task 0)
**Produces:** CRUD jadwal + deteksi bentrok jam guru.

`day_of_week` adalah **tinyint 1-5** (Senin-Jumat, D14), bukan string. Rule validasi memakai `array_map` dari enum `DayOfWeek` supaya angka 1-5 dan label "Senin".."Jumat" tidak pernah menyimpang. Komponen menyimpan nilainya sebagai string karena `<select>` selalu mengirim string; konversi ke `int` terjadi di service.

Deteksi bentrok **tidak** ditulis ulang di sini. `ScheduleManager` memanggil `ScheduleConflictDetector::findConflicts()` yang sudah diuji di Plan 1 Task 6, lalu melempar `ValidationException` dengan nama kelas, mapel, hari, dan jam yang bentrok. Yang diuji ulang di plan ini adalah dua hal: pesan bentrok menyebut jadwal yang bentrok, dan mengarsipkan jadwal membebaskan slot jam itu.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/ScheduleManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\DayOfWeek;
use App\Livewire\ScheduleManagement;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use App\Support\ScheduleConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $mapel = Subject::factory()->create();
        $guru = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', $kelas->id)
            ->set('subject_id', $mapel->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasNoErrors();

        $jadwal = Schedule::sole();

        $this->assertSame($kelas->id, $jadwal->class_id);
        $this->assertSame($mapel->id, $jadwal->subject_id);
        $this->assertSame($guru->id, $jadwal->teacher_id);
        $this->assertSame(DayOfWeek::Senin, $jadwal->day_of_week);
        $this->assertSame('07:00', $jadwal->start_time->format('H:i'));
        $this->assertSame('08:30', $jadwal->end_time->format('H:i'));
    }

    public function test_jadwal_bentrok_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '08:00')
            ->set('end_time', '09:00')
            ->call('save')
            ->assertHasErrors(['start_time']);

        $this->assertSame(1, Schedule::count());
    }

    public function test_pesan_bentrok_menyebut_kelas_dan_jam(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        try {
            app(ScheduleManager::class)->save(null, [
                'class_id' => SchoolClass::factory()->create()->id,
                'subject_id' => $mapel->id,
                'teacher_id' => $guru->id,
                'day_of_week' => '1',
                'start_time' => '08:00',
                'end_time' => '09:00',
            ]);

            $this->fail('Jamnya bertindih, seharusnya ditolak.');
        } catch (ValidationException $e) {
            $pesan = $e->errors()['start_time'][0];

            $this->assertStringContainsString('VII-A', $pesan);
            $this->assertStringContainsString('Matematika', $pesan);
            $this->assertStringContainsString('Senin', $pesan);
            $this->assertStringContainsString('07:00-08:30', $pesan);
        }
    }

    public function test_jadwal_sepada_tetap_diterima(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '08:30')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Schedule::count());
    }

    public function test_hari_di_luar_senin_jumat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', User::factory()->guru()->create()->id)
            ->set('day_of_week', '6')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasErrors(['day_of_week']);

        $this->assertSame(0, Schedule::count());
    }

    public function test_jam_selesai_harus_setelah_jam_mulai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', User::factory()->guru()->create()->id)
            ->set('day_of_week', '1')
            ->set('start_time', '10:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasErrors(['end_time']);

        $this->assertSame(0, Schedule::count());
    }

    public function test_arsipkan_jadwal_membebaskan_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->call('arsipkan', $jadwal->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('schedules', ['id' => $jadwal->id]);

        $bentrok = app(ScheduleConflictDetector::class)
            ->findConflicts($guru, DayOfWeek::Senin->value, '07:00:00', '08:30:00');

        $this->assertSame([], $bentrok);
    }

    public function test_arsipkan_jadwal_tidak_menghapus_absensi_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)->test(ScheduleManagement::class)->call('arsipkan', $jadwal->id);

        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_guru_tidak_bisa_membuka_halaman_jadwal(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/jadwal')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_jadwal(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/jadwal')
            ->assertOk()
            ->assertSee('Jadwal Pelajaran');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=ScheduleManagementTest
```

Expected: FAIL — `Unable to find component: ScheduleManagement`.

- [ ] **Step 3: Tulis service, komponen, dan view**

`app/Services/ScheduleManager.php`:

```php
<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Support\ScheduleConflictDetector;
use Illuminate\Validation\ValidationException;

final class ScheduleManager
{
    public function __construct(private readonly ScheduleConflictDetector $detector) {}

    /**
     * @param  array{class_id: string, subject_id: string, teacher_id: string, day_of_week: string, start_time: string, end_time: string}  $data
     */
    public function save(?Schedule $schedule, array $data): Schedule
    {
        $guru = User::findOrFail($data['teacher_id']);
        $hari = DayOfWeek::from((int) $data['day_of_week']);

        $bentrok = $this->detector->findConflicts(
            $guru,
            $hari->value,
            $data['start_time'],
            $data['end_time'],
            $schedule?->id,
        );

        if ($bentrok !== []) {
            throw ValidationException::withMessages([
                'start_time' => $this->pesanBentrok($bentrok),
            ]);
        }

        $jadwal = $schedule ?? new Schedule;

        $jadwal->fill([
            'class_id' => $data['class_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $guru->id,
            'day_of_week' => $hari,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);
        $jadwal->save();

        return $jadwal;
    }

    public function arsipkan(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function pulihkan(Schedule $schedule): void
    {
        $schedule->restore();
    }

    /**
     * @param  array<int,Schedule>  $bentrok
     */
    private function pesanBentrok(array $bentrok): string
    {
        $rincian = collect($bentrok)
            ->map(fn (Schedule $j) => sprintf(
                '%s - %s (%s %s-%s)',
                $j->schoolClass->name,
                $j->subject->name,
                $j->day_of_week->label(),
                $j->start_time->format('H:i'),
                $j->end_time->format('H:i'),
            ))
            ->implode('; ');

        return "Jam guru sudah dipakai: {$rincian}.";
    }
}
```

`$schedule?->id` sebagai argumen `ignoreScheduleId` membuat edit tidak bentrok dengan dirinya sendiri. Soft delete membebaskan slot karena `ScheduleConflictDetector` memakai `Schedule::query()` yang menerapkan global scope soft delete.

**Jangan baca relasi dari model yang datang dari query banyak baris.** `Model::shouldBeStrict()` di `AppServiceProvider` menyalakan guard lazy loading, tapi `Eloquent\Builder::hydrate()` hanya menerapkannya kalau `count($items) > 1`. Akibatnya kode yang salah lolos di test dengan fixture satu baris, lalu meledak 500 di aplikasi nyata yang umumnya punya banyak baris. `findConflicts()` karena itu wajib `->with(['schoolClass', 'subject'])`, dan `ScheduleManager::pesanBentrok()` boleh aman. Aturan yang sama berlaku untuk `AttendanceRecorder::record()` dan `StudentPolicy::view()`: keduanya kini memakai `class_id` langsung, bukan `$schedule->schoolClass` atau `$student->schoolClass`.

`app/Livewire/ScheduleManagement.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ScheduleManagement extends Component
{
    public ?string $editingId = null;

    public string $academic_year_id = '';

    public string $class_id = '';

    public string $subject_id = '';

    public string $teacher_id = '';

    public string $day_of_week = '1';

    public string $start_time = '';

    public string $end_time = '';

    public bool $showArchived = false;

    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'class_id' => ['required', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'teacher_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('role', UserRole::Guru)
                        ->whereNull('deleted_at')),
            ],
            'day_of_week' => [
                'required',
                Rule::in(array_map(fn (DayOfWeek $hari) => (string) $hari->value, DayOfWeek::cases())),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('create', Schedule::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::query()
                ->where('is_active', true)
                ->value('id') ?? AcademicYear::query()->orderByDesc('start_date')->value('id'));
        }
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->class_id = '';
        $this->subject_id = '';
        $this->teacher_id = '';
        $this->day_of_week = '1';
        $this->start_time = '';
        $this->end_time = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $jadwal = Schedule::withTrashed()->findOrFail($id);

        Gate::authorize('update', $jadwal);

        $this->editingId = $jadwal->id;
        $this->academic_year_id = $jadwal->schoolClass->academic_year_id;
        $this->class_id = $jadwal->class_id;
        $this->subject_id = $jadwal->subject_id;
        $this->teacher_id = $jadwal->teacher_id;
        $this->day_of_week = (string) $jadwal->day_of_week->value;
        $this->start_time = $jadwal->start_time->format('H:i');
        $this->end_time = $jadwal->end_time->format('H:i');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $jadwal = $this->editingId === null ? null : Schedule::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($jadwal === null ? 'create' : 'update', $jadwal ?? Schedule::class);

        $baru = $jadwal === null;

        app(ScheduleManager::class)->save($jadwal, [
            'class_id' => $this->class_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Jadwal ditambahkan.' : 'Jadwal diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $jadwal = Schedule::findOrFail($id);

        Gate::authorize('delete', $jadwal);

        app(ScheduleManager::class)->arsipkan($jadwal);

        session()->flash('sukses', 'Jadwal diarsipkan. Absensi lama pada jadwal ini tetap utuh.');
    }

    public function pulihkan(string $id): void
    {
        $jadwal = Schedule::withTrashed()->findOrFail($id);

        Gate::authorize('update', $jadwal);

        app(ScheduleManager::class)->pulihkan($jadwal);

        session()->flash('sukses', 'Jadwal dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Schedule::class);

        $kelasTerpilih = SchoolClass::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return view('livewire.schedule-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'daftarKelas' => $kelasTerpilih,
            'daftarMapel' => Subject::query()->orderBy('name')->get(),
            'daftarGuru' => User::query()
                ->where('role', UserRole::Guru)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'hari' => DayOfWeek::cases(),
            'daftar' => Schedule::query()
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->whereHas('schoolClass', fn ($query) => $query
                    ->where('academic_year_id', $this->academic_year_id))
                ->with('schoolClass')
                ->with('subject')
                ->with('teacher')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(),
        ])->title('Jadwal Pelajaran');
    }
}
```

Daftar jadwal di-scope ke `academic_year_id` dengan `whereHas('schoolClass', ...)`, bukan dengan kondisi role. Kelas berbeda tahun ajaran adalah baris berbeda (D2), jadi mencampurkannya di satu tabel akan membuat guru mengira dia mengajar dua kelas yang sebenarnya kelas yang sama di dua tahun.

`mount()` memakai `Gate::authorize('create', Schedule::class)`, **bukan** `viewAny`. `SchedulePolicy::viewAny` sengaja mengizinkan guru - guru butuh akses baca jadwalnya sendiri untuk absensi di Plan 4 - sedangkan `/master/jadwal` adalah layar management yang link nav-nya `adminOnly`. Kalau `mount()` memakai `viewAny`, guru bisa membuka halaman management dan test 403 selalu gagal. Aturan yang sama berlaku untuk Task 5 dan Task 6: layar management mengotorisasi ability yang admin-only, bukan `viewAny` sebuah policy yang longgar.

Assertion jam tidak boleh pakai `assertDatabaseHas()` dengan `'07:00:00'`. Cast `start_time`/`end_time` di model `Schedule` adalah `datetime:H:i`, jadi yang tersimpan di database adalah `07:00`, bukan `07:00:00`. Ambil lewat model lalu `->format('H:i')` - aturan yang sama dengan assertion tanggal di Task 1.

`resources/views/livewire/schedule-management.blade.php`:

```blade
<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Jadwal Pelajaran</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
    @endif

    @error('start_time')
        <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="font-medium text-gray-900">
                {{ $editingId === null ? 'Tambah Jadwal' : 'Ubah Jadwal' }}
            </h2>

            <label class="block text-sm">
                <span class="text-gray-700">Tahun Ajaran</span>
                <select wire:model.live="academic_year_id" class="mt-1 w-full rounded border-gray-300">
                    @foreach ($tahunAjaran as $tahun)
                        <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Kelas</span>
                <select wire:model="class_id" class="mt-1 w-full rounded border-gray-300">
                    <option value="">Pilih kelas</option>
                    @foreach ($daftarKelas as $kelas)
                        <option value="{{ $kelas->id }}">{{ $kelas->name }}</option>
                    @endforeach
                </select>
                @error('class_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Mata Pelajaran</span>
                <select wire:model="subject_id" class="mt-1 w-full rounded border-gray-300">
                    <option value="">Pilih mata pelajaran</option>
                    @foreach ($daftarMapel as $mapel)
                        <option value="{{ $mapel->id }}">{{ $mapel->name }}</option>
                    @endforeach
                </select>
                @error('subject_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Guru Pengajar</span>
                <select wire:model="teacher_id" class="mt-1 w-full rounded border-gray-300">
                    <option value="">Pilih guru</option>
                    @foreach ($daftarGuru as $guru)
                        <option value="{{ $guru->id }}">{{ $guru->name }}</option>
                    @endforeach
                </select>
                @error('teacher_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-700">Hari</span>
                <select wire:model="day_of_week" class="mt-1 w-full rounded border-gray-300">
                    @foreach ($hari as $item)
                        <option value="{{ $item->value }}">{{ $item->label() }}</option>
                    @endforeach
                </select>
                @error('day_of_week') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block text-sm">
                    <span class="text-gray-700">Mulai</span>
                    <input type="time" wire:model="start_time" class="mt-1 w-full rounded border-gray-300" />
                    @error('start_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-700">Selesai</span>
                    <input type="time" wire:model="end_time" class="mt-1 w-full rounded border-gray-300" />
                    @error('end_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Simpan</button>
                <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <div class="lg:col-span-2 space-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:click="toggleArsip" />
                Tampilkan yang diarsipkan
            </label>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Hari</th>
                            <th class="px-4 py-2">Jam</th>
                            <th class="px-4 py-2">Kelas</th>
                            <th class="px-4 py-2">Mapel</th>
                            <th class="px-4 py-2">Guru</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $jadwal)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-2">{{ $jadwal->day_of_week->label() }}</td>
                                <td class="px-4 py-2">
                                    {{ $jadwal->start_time->format('H:i') }} - {{ $jadwal->end_time->format('H:i') }}
                                </td>
                                <td class="px-4 py-2">{{ $jadwal->schoolClass->name }}</td>
                                <td class="px-4 py-2">{{ $jadwal->subject->name }}</td>
                                <td class="px-4 py-2">{{ $jadwal->teacher->name }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button wire:click="editForm('{{ $jadwal->id }}')" class="text-sm text-blue-700">Ubah</button>
                                    @if ($jadwal->trashed())
                                        <button wire:click="pulihkan('{{ $jadwal->id }}')" class="ml-2 text-sm text-green-700">Pulihkan</button>
                                    @else
                                        <button wire:click="arsipkan('{{ $jadwal->id }}')" class="ml-2 text-sm text-red-700">Arsipkan</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    Belum ada jadwal pada tahun ajaran ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=ScheduleManagementTest
```

**Expected: 11 tests pass** (8 test di Task 4 + 3 test tambahan: arsip tidak menghapus absensi lama, guru dapat 403, admin dapat 200). Angka 11 sudah diverifikasi dengan `php artisan test --filter=ScheduleManagementTest` di branch `feat/plan-3-data-master`.

Kalau `test_arsipkan_jadwal_membebaskan_slot` gagal dengan `$bentrok` berisi 1 jadwal, `ScheduleConflictDetector` tidak menerapkan global scope soft delete — periksa `use Schedule::query()`, bukan `Schedule::withTrashed()`.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: CRUD jadwal dengan deteksi bentrok jam guru dan arsip"
```

---

---

## Task 5: `StudentManagement`

**Files:**
- Create: `app/Services/StudentManager.php`
- Create: `app/Livewire/StudentManagement.php`
- Create: `resources/views/livewire/student-management.blade.php`
- Test: `tests/Feature/Livewire/StudentManagementTest.php`

**Consumes:** `Student`, `SchoolClass`, `Attendance`, `StudentGender`, `StudentPolicy` (Plan 1 Task 4, dipakai apa adanya)
**Produces:** CRUD siswa + pencarian + filter kelas/jenis kelamin + arsip dan pemulihan.

Kolom `students` persis seperti Plan 1 Task 2: `class_id`, `nisn`, `name`, `gender`. Tidak ada kolom alamat, tanggal lahir, atau telepon — data itu tidak dipakai laporan mana pun di v1, dan menambahkannya berarti mengubah migration yang sudah jadi.

`students.nisn` punya unique index di DB. Index itu **tidak** menyertakan `deleted_at`, jadi NISN siswa yang diarsipkan atau yang sudah purnawawan akan tetap menutup NISN itu selamanya. Ini konsekuensi yang disengaja: mengosongkan baris soft-deleted demi "membebaskan NISN" adalah bypass unique constraint. Pemulihan lewat tombol Pulihkan adalah satu-satunya jalan.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/StudentManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\StudentGender;
use App\Livewire\StudentManagement;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', $kelas->id)
            ->set('nisn', '3200123456')
            ->set('name', 'Budi Santoso')
            ->set('gender', StudentGender::L->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'class_id' => $kelas->id,
            'nisn' => '3200123456',
            'name' => 'Budi Santoso',
            'gender' => 'L',
        ]);
    }

    public function test_nisn_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        Student::factory()->create(['nisn' => '3200123456']);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', $kelas->id)
            ->set('nisn', '3200123456')
            ->set('name', 'Budi Santoso')
            ->set('gender', StudentGender::L->value)
            ->call('save')
            ->assertHasErrors(['nisn']);

        $this->assertSame(1, Student::count());
    }

    public function test_kelas_dan_jenis_kelamin_wajib_diisi(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('class_id', '')
            ->set('nisn', '3200999999')
            ->set('name', 'Sari Wulandari')
            ->set('gender', 'X')
            ->call('save')
            ->assertHasErrors(['class_id', 'gender']);

        $this->assertSame(0, Student::count());
    }

    public function test_siswa_diarsipkan_riwayat_absensinya_tetap_utuh(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->call('arsipkan', $siswa->id)
            ->assertSee('punya riwayat absensi');

        $this->assertSoftDeleted('students', ['id' => $siswa->id]);
        $this->assertFalse(Student::query()->whereKey($siswa->id)->exists());
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_siswa_arsip_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = Student::factory()->create(['nisn' => '3200123456']);
        $siswa->delete();

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('showArchived', true)
            ->call('pulihkan', $siswa->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('students', ['id' => $siswa->id]);
    }

    public function test_pencarian_dan_filter_kelas_dan_jenis_kelamin(): void
    {
        $admin = User::factory()->admin()->create();
        $kelasA = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelasB = SchoolClass::factory()->create(['name' => 'VII-B']);

        Student::factory()->create([
            'class_id' => $kelasA->id,
            'nisn' => '1001',
            'name' => 'Budi Santoso',
            'gender' => StudentGender::L,
        ]);
        Student::factory()->create([
            'class_id' => $kelasB->id,
            'nisn' => '1002',
            'name' => 'Sari Wulandari',
            'gender' => StudentGender::P,
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Sari Wulandari')
            ->set('search', '1002')
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso')
            ->set('search', '')
            ->set('filterClassId', $kelasB->id)
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso')
            ->set('filterClassId', '')
            ->set('filterGender', StudentGender::P->value)
            ->assertSee('Sari Wulandari')
            ->assertDontSee('Budi Santoso');
    }

    public function test_siswa_naik_kelas_menyalin_nisn_tetap_valid(): void
    {
        $admin = User::factory()->admin()->create();
        $kelasA = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelasB = SchoolClass::factory()->create(['name' => 'VIII-A']);
        $siswa = Student::factory()->create([
            'class_id' => $kelasA->id,
            'nisn' => '3200123456',
            'name' => 'Budi Santoso',
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->call('editForm', $siswa->id)
            ->set('class_id', $kelasB->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'id' => $siswa->id,
            'class_id' => $kelasB->id,
            'nisn' => '3200123456',
        ]);
    }

    public function test_daftar_kelas_menampilkan_tahun_ajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create(['name' => '2025/2026']);
        SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'name' => 'VII-A',
        ]);

        Livewire::actingAs($admin)
            ->test(StudentManagement::class)
            ->assertSee('VII-A')
            ->assertSee('2025/2026');
    }

    public function test_guru_tidak_bisa_membuka_halaman_siswa(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/siswa')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_siswa(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/siswa')
            ->assertOk()
            ->assertSee('Siswa');
    }
}
```

`test_siswa_diarsipkan_riwayat_absensinya_tetap_utuh` tidak boleh memakai `assertSame(0, Student::count())`. `AttendanceFactory::definition()` membuat jadwal **dan** siswa sendiri supaya kelas siswa sama dengan kelas jadwal, jadi ada siswa tambahan dari factory. Yang dibuktikan test ini adalah "siswa ini tidak lagi aktif", bukan "tidak ada siswa sama sekali" - itu assertion `Student::query()->whereKey($siswa->id)->exists()`. Bug yang sama sudah muncul di Task 3 dengan `AttendanceFactory`.

`test_daftar_kelas_menampilkan_tahun_ajaran` menjaga label kelas di pilihan kelas dan di tabel. Kelas dibuat ulang tiap tahun ajaran (spec D2), jadi nama `VII-A` ada di lebih dari satu baris `classes`; menampilkan `VII-A` saja membuat admin tidak bisa tahu kelas tahun berapa yang dimaksud. Karena itu `daftarKelas` dan tabel siswa memuat `academicYear->name`, dan `tahunAjaran` yang tidak terpakai di versi lama plan dihapus dari data view.

`test_siswa_naik_kelas_menyalin_nisn_tetap_valid` adalah penjaga `Rule::unique(...)->ignore($this->editingId)`. Tanpa `ignore`, memindahkan siswa dengan NISN yang sama selalu gagal dengan "NISN sudah terdaftar" — padahal itu siswa yang sama, bukan duplikat. Pindah kelas adalah **update `class_id`**, bukan baris baru (spec §3).

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=StudentManagementTest
```

Expected: FAIL — `Unable to find component: StudentManagement`.

- [ ] **Step 3: Tulis service, komponen, dan view**

`app/Services/StudentManager.php`:

```php
<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class StudentManager
{
    /**
     * @param  array{class_id: string, nisn: string, name: string, gender: string}  $data
     */
    public function save(?Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $siswa = $student ?? new Student;

            $siswa->fill([
                'class_id' => $data['class_id'],
                'nisn' => $data['nisn'],
                'name' => $data['name'],
                'gender' => $data['gender'],
            ]);
            $siswa->save();

            return $siswa;
        });
    }

    public function arsipkan(Student $student): void
    {
        $student->delete();
    }

    public function pulihkan(Student $student): void
    {
        $student->restore();
    }
}
```

`app/Livewire/StudentManagement.php`:

```php
<?php

namespace App\Livewire;

use App\Enums\StudentGender;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class StudentManagement extends Component
{
    public ?string $editingId = null;

    public string $class_id = '';

    public string $nisn = '';

    public string $name = '';

    public string $gender = 'L';

    public string $search = '';

    public string $filterClassId = '';

    public string $filterGender = '';

    public bool $showArchived = false;

    public int $page = 1;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'class_id' => ['required', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'nisn' => [
                'required',
                'string',
                'max:20',
                Rule::unique('students', 'nisn')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'gender' => [
                'required',
                Rule::in(array_map(fn (StudentGender $item) => $item->value, StudentGender::cases())),
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedFilterClassId(): void
    {
        $this->page = 1;
    }

    public function updatedFilterGender(): void
    {
        $this->page = 1;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->class_id = '';
        $this->nisn = '';
        $this->name = '';
        $this->gender = StudentGender::L->value;
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $siswa = Student::withTrashed()->findOrFail($id);

        Gate::authorize('update', $siswa);

        $this->editingId = $siswa->id;
        $this->class_id = $siswa->class_id;
        $this->nisn = $siswa->nisn;
        $this->name = $siswa->name;
        $this->gender = $siswa->gender->value;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $siswa = $this->editingId === null ? null : Student::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($siswa === null ? 'create' : 'update', $siswa ?? Student::class);

        $baru = $siswa === null;

        app(StudentManager::class)->save($siswa, [
            'class_id' => $this->class_id,
            'nisn' => $this->nisn,
            'name' => $this->name,
            'gender' => $this->gender,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Siswa ditambahkan.' : 'Siswa diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $siswa = Student::findOrFail($id);

        Gate::authorize('delete', $siswa);

        app(StudentManager::class)->arsipkan($siswa);

        session()->flash('sukses', "{$siswa->name} diarsipkan, bukan dihapus permanen. Siswa ini punya riwayat absensi yang harus tetap bisa dibaca.");
    }

    public function pulihkan(string $id): void
    {
        $siswa = Student::withTrashed()->findOrFail($id);

        Gate::authorize('update', $siswa);

        app(StudentManager::class)->pulihkan($siswa);

        session()->flash('sukses', "{$siswa->name} dipulihkan.");
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
        $this->page = 1;
    }

    public function render(): View
    {
        Gate::authorize('create', Student::class);

        return view('livewire.student-management', [
            'daftarKelas' => SchoolClass::query()
                ->with('academicYear')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(),
            'jenisKelamin' => StudentGender::cases(),
            'siswa' => $this->daftarSiswa()
                ->with('schoolClass.academicYear')
                ->orderBy('nisn')
                ->forPage($this->page, 15)
                ->get(),
            'totalSiswa' => $this->daftarSiswa()->count(),
        ])->title('Siswa');
    }

    /**
     * Daftar dan total harus memakai filter yang sama, jadi rantainya
     * dibangun di satu tempat. Kalau dirantai dua kali, filter yang
     * berubah di salah satunya membuat total tidak cocok dengan isi tabel.
     *
     * @return Builder<Student>
     */
    private function daftarSiswa(): Builder
    {
        return Student::query()
            ->when($this->showArchived, fn (Builder $query) => $query->onlyTrashed())
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('nisn', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%');
            }))
            ->when($this->filterClassId !== '', fn (Builder $query) => $query->where('class_id', $this->filterClassId))
            ->when($this->filterGender !== '', fn (Builder $query) => $query->where('gender', $this->filterGender));
    }
}
```

Guard di `render()` memakai ability `create`, bukan `viewAny`. `StudentPolicy::viewAny` mengizinkan guru yang punya jadwal — itu benar untuk dashboard Plan 4, tapi halaman ini layar admin, jadi yang diuji adalah hak membuat. Guru yang lolos lewat `viewAny` tetap ditolak di sini, dan `StudentPolicy::create` tetap satu-satunya sumber kebenaran. Component ini tidak punya `mount()`: tidak ada state yang perlu diinisialisasi dari URL, jadi guard di `render()` sudah menutup halaman dan tetap ikut jalan pada request AJAX Livewire.

Paginasi 15 baris per halaman dikendalikan property `page`, bukan trait `WithPagination`, supaya component ini tidak bergantung pada API trait yang berubah antar major Livewire. Filter dan pencarian di-scope di query builder, tidak di view. 210 siswa sudah cukup untuk membuat filter di frontend terasa salah: baris yang tidak cocok masih ikut ter-render. Rantai filter diambil dari method `daftarSiswa()` supaya tabel dan `totalSiswa` tidak bisa berbeda karena salah satu rantai lupa diubah.

`resources/views/livewire/student-management.blade.php`:

```blade
<div class="space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Siswa</h1>

    @if (session('sukses'))
        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-5">
        <label class="block text-sm">
            <span class="text-gray-700">NISN</span>
            <input type="text" wire:model="nisn" class="mt-1 w-full rounded border-gray-300" />
            @error('nisn') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700">Nama</span>
            <input type="text" wire:model="name" class="mt-1 w-full rounded border-gray-300" />
            @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700">Jenis Kelamin</span>
            <select wire:model="gender" class="mt-1 w-full rounded border-gray-300">
                @foreach ($jenisKelamin as $item)
                    <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                @endforeach
            </select>
            @error('gender') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="text-gray-700">Kelas</span>
            <select wire:model="class_id" class="mt-1 w-full rounded border-gray-300">
                <option value="">Pilih kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                @endforeach
            </select>
            @error('class_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">
                {{ $editingId === null ? 'Tambah' : 'Perbarui' }}
            </button>
            <button type="button" wire:click="newForm" class="rounded border px-3 py-2 text-sm">Reset</button>
        </div>
    </form>

    <div class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4">
        <label class="block text-sm">
            <span class="text-gray-700">Cari NISN atau Nama</span>
            <input type="search" wire:model.live.debounce.300ms="search"
                class="mt-1 w-full rounded border-gray-300" />
        </label>

        <label class="block text-sm">
            <span class="text-gray-700">Filter Kelas</span>
            <select wire:model.live="filterClassId" class="mt-1 w-full rounded border-gray-300">
                <option value="">Semua kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}">{{ $kelas->name }} ({{ $kelas->academicYear->name }})</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="text-gray-700">Filter Jenis Kelamin</span>
            <select wire:model.live="filterGender" class="mt-1 w-full rounded border-gray-300">
                <option value="">Semua</option>
                @foreach ($jenisKelamin as $item)
                    <option value="{{ $item->value }}">{{ $item->value === 'L' ? 'Laki-laki' : 'Perempuan' }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-end gap-2 text-sm text-gray-700">
            <input type="checkbox" wire:click="toggleArsip" />
            Tampilkan yang diarsipkan
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">NISN</th>
                    <th class="px-4 py-2">Nama</th>
                    <th class="px-4 py-2">L/P</th>
                    <th class="px-4 py-2">Kelas</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswa as $item)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2">{{ $item->nisn }}</td>
                        <td class="px-4 py-2">
                            {{ $item->name }}
                            @if ($item->trashed())
                                <span class="ml-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Diarsipkan</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $item->gender->value }}</td>
                        <td class="px-4 py-2">
                            {{ $item->schoolClass->name }} ({{ $item->schoolClass->academicYear->name }})
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="editForm('{{ $item->id }}')" class="text-sm text-blue-700">Ubah</button>
                            @if ($item->trashed())
                                <button wire:click="pulihkan('{{ $item->id }}')" class="ml-2 text-sm text-green-700">Pulihkan</button>
                            @else
                                <button wire:click="arsipkan('{{ $item->id }}')" class="ml-2 text-sm text-red-700">Arsipkan</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                            Tidak ada siswa yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between text-sm text-gray-600">
        <span>{{ $totalSiswa }} siswa</span>
        <span class="flex gap-2">
            <button wire:click="gotoPage({{ $page - 1 }})" @disabled($page <= 1)
                class="rounded border px-3 py-1 disabled:opacity-40">Sebelumnya</button>
            <span>Halaman {{ $page }}</span>
            <button wire:click="gotoPage({{ $page + 1 }})" @disabled($page * 15 >= $totalSiswa)
                class="rounded border px-3 py-1 disabled:opacity-40">Berikutnya</button>
        </span>
    </div>
</div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=StudentManagementTest
```

**Expected: 10 tests pass** (8 test di Task 5 + 2 test tambahan: label kelas menampilkan tahun ajaran, admin dapat 200). Angka 10 sudah diverifikasi dengan `php artisan test --filter=StudentManagementTest` di branch `feat/plan-3-data-master`.

Kalau `test_pencarian_dan_filter_kelas_dan_jenis_kelamin` gagal, filter sedang dijalankan di view (misalnya `@if` per baris) alih-alih di query builder.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: CRUD siswa dengan pencarian, filter, arsip, dan pemulihan"
```

---

---

## Task 6: `StudentImport` — impor Excel siswa

**Files:**
- Create: `app/Imports/StudentSheetReader.php`
- Create: `app/Imports/StudentRowValidator.php`
- Create: `app/Services/StudentImporter.php`
- Create: `app/Livewire/StudentImport.php`
- Create: `resources/views/livewire/student-import.blade.php`
- Create: `tests/Support/StudentRowsSheet.php`
- Modify: `routes/master.php` (tambah `Route::get('siswa/impor', StudentImport::class)->name('siswa.impor');`)
- Modify: `resources/views/components/nav/data-master.blade.php` (link "Impor Siswa", `adminOnly`)
- Test: `tests/Feature/Livewire/StudentImportTest.php`

**Consumes:** `maatwebsite/excel` + `config/excel.php` (Plan 1 Task 8), `Student`, `SchoolClass`, `AcademicYear`, `StudentGender`, `StudentPolicy::create` (Plan 1 Task 4)
**Produces:** alur unggah Excel siswa dengan pratinjau dua kolom (akan disimpan / ditolak) dan penyimpanan yang **penuh atau gagal sama sekali**.

Aturan alur yang tidak boleh ditawar:

1. **Tahun ajaran dipilih lebih dulu, baru file.** Nama kelas yang sama ada di setiap tahun ajaran, jadi "Kelas" di file hanya bermakna kalau sudah diketahui kelasnya yang mana. Tanpa ini, satu baris bisa cocok ke dua kelas dan siswa masuk ke tahun yang salah.
2. **Tidak ada simpan sebagian.** Kalau satu baris ditolak, tombol simpan mati dan `confirm()` melempar `ValidationException`. 210 siswa masuk 209 dan diam-diam satu hilang adalah kegagalan data yang jauh lebih sulit aeroporto daripada import yang gagal.
3. **Satu transaksi.** `StudentImporter::import()` membungkus seluruhnya; satu baris gagal berarti nol baris tersimpan.
4. **NISN dicegok dua kali**: terhadap database (termasuk yang diarsipkan, karena index unique tidak melihat `deleted_at`) dan terhadap baris-baris sebelumnya di file yang sama.

Header yang dibaca: baris 1 sebagai judul kolom, dengan beberapa ejaan nama kolom diterima (`NISN`, `Nama`/`Nama Lengkap`, `JK`/`Jenis Kelamin`/`LP`, `Kelas`/`Class`). Nilai jenis kelamin menerima `L`/`Laki-laki`/`P`/`Perempuan` karena file dari tata usaha sering masih memakai kata, bukan huruf.

Tidak ada file konfigurasi baru di task ini. `config/excel.php` sudah ada dari Plan 1 Task 8, disk temp memakai disk bawaan Laravel, dan tidak ada antrean karena `QUEUE_CONNECTION=sync`.

Dua jebakan API Maatwebsite Excel v4 yang sudah terverifikasi saat task ini dikerjakan:

- Konstanta reader bukan `Excel::TYPE_XLSX`, tapi `Maatwebsite\Excel\Excel::XLSX`. `Excel::import($import, $path, null, ExcelType::XLSX)` dan `Excel::raw($export, ExcelType::XLSX)`.
- Helper test harus mengembalikan `Illuminate\Http\Testing\File`, bukan `Illuminate\Http\UploadedFile`. `Livewire\Features\SupportTesting\Testable::upload()` membaca properti publik `$file->name` yang hanya ada di `Testing\File`; `UploadedFile` biasa akan meledak dengan `Undefined property: ...::$name`. `File::createWithContent('data-siswa.xlsx', $bytes)` memberi file dengan isi xlsx asli sekaligus properti tersebut.
- Tiap baris dari `ToCollection` adalah `Illuminate\Support\Collection`, bukan array. `(array) $baris` akan menghasilkan properti privat Collection (`items`, `escapeWhenCastingToString`) sehingga kolom kosong semua dan validasi gagal diam-diam; pakai `$baris->all()`. Judul kolom juga sudah di-slug `imports.heading_row.formatter` dari `config/excel.php`, jadi `Nama Lengkap` menjadi `nama_lengkap`.

- [ ] **Step 1: Tulis test yang gagal**

Helper pembuat file `.xlsx` asli, supaya yang diuji benar-benar parsing Excel dan bukan mock:

`tests/Support/StudentRowsSheet.php`:

```php
<?php

namespace Tests\Support;

use Maatwebsite\Excel\Concerns\FromArray;

final class StudentRowsSheet implements FromArray
{
    /**
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }
}
```

`tests/Feature/Livewire/StudentImportTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\StudentImport;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\StudentRowsSheet;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function xlsx(array $rows): File
    {
        return File::createWithContent(
            'data-siswa.xlsx',
            Excel::raw(new StudentRowsSheet($rows), ExcelType::XLSX)
        );
    }

    private function header(): array
    {
        return ['NISN', 'Nama', 'JK', 'Kelas'];
    }

    public function test_file_valid_mengimpor_seluruh_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000002', 'Sari Wulandari', 'P', 'VII-A'],
        ]);

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->assertHasNoErrors()
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame(2, Student::count());
        $this->assertDatabaseHas('students', ['nisn' => '3200000001', 'gender' => 'L', 'name' => 'Budi Santoso']);
        $this->assertDatabaseHas('students', ['nisn' => '3200000002', 'gender' => 'P', 'name' => 'Sari Wulandari']);
    }

    public function test_baris_tidak_valid_menampilkan_alasan_dan_tidak_menyimpan_apa_pun(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['', 'Tanpa NISN', 'L', 'VII-A'],
            ['3200000003', 'Kelas Salah', 'L', 'VII-Z'],
            ['3200000004', 'Gender Salah', 'X', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->assertHasNoErrors()
            ->assertSee('NISN wajib diisi.')
            ->assertSee("Kelas 'VII-Z' tidak ditemukan", false);

        $this->assertSame(3, $komponen->instance()->barisGagal());
        $this->assertSame(1, $komponen->instance()->barisValid());

        $komponen->call('confirm')->assertHasErrors(['file']);

        $this->assertSame(0, Student::count());
    }

    public function test_file_sebagian_valid_ditolak_tidak_disimpan_sebagian(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000002', 'Sari Wulandari', 'L', 'Kelas Tidak Ada'],
        ]);

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm')
            ->assertHasErrors(['file']);

        $this->assertSame(0, Student::count());
    }

    public function test_nisn_kembar_di_dalam_file_yang_sama_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000001', 'Budi Santoso Dua', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview');

        $barisGagal = $komponen->instance()->barisGagal();

        $this->assertCount(1, $barisGagal);
        $this->assertSame(3, $barisGagal[0]['row']);
        $this->assertStringContainsString('3200000001', $barisGagal[0]['errors'][0]);
        $this->assertSame(0, Student::count());
    }

    public function test_nisn_yang_sudah_terdaftar_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);
        Student::factory()->create(['class_id' => $kelas->id, 'nisn' => '3200000001']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview');

        $this->assertStringContainsString(
            'sudah terdaftar',
            $komponen->instance()->barisGagal()[0]['errors'][0]
        );
    }

    public function test_tahun_ajaran_dan_file_wajib_diisi(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->call('preview')
            ->assertHasErrors(['academic_year_id', 'file']);

        $this->assertSame(0, Student::count());
    }

    public function test_guru_tidak_bisa_membuka_halaman_impor(): void
    {
        $this->expectException(AuthorizationException::class);

        Livewire::actingAs(User::factory()->guru()->create())
            ->test(StudentImport::class);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=StudentImportTest
```

Expected: FAIL — `Unable to find component: StudentImport`.

- [ ] **Step 3: Tulis pembaca, validator, dan importer**

`app/Imports/StudentSheetReader.php`:

```php
<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

final class StudentSheetReader implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<array-key, mixed>> */
    private array $rows = [];

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Reader Laravel Excel mengirim tiap baris sebagai `Collection`, bukan array.
     * `(array) $baris` pada objek Collection menghasilkan properti privatnya
     * (`items`, `escapeWhenCastingToString`), bukan pasangan kolom, jadi barisnya
     * harus diambil lewat `all()`.
     *
     * Dipanggil sekali per chunk oleh reader, jadi setiap potongan ditumpuk,
     * bukan ditimpa.
     *
     * @param  Collection<array-key, mixed>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[] = $row instanceof Collection
                ? $row->all()
                : (is_array($row) ? $row : []);
        }
    }

    /**
     * @return array<int, array<array-key, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
```

**Kenapa tidak ada `ShouldQueue`:** `QUEUE_CONNECTION=sync` di shared hosting, jadi impor berjalan di request yang sama. Dengan `reader.chunkSize` bawaan 100 pada `config/excel.php`, `collection()` dipanggil beberapa kali untuk file besar; itulah alasan `StudentSheetReader` menumpuk chunk. Untuk 200-300 siswa sekolah mitra, satu request sudah cukup.

`app/Imports/StudentRowValidator.php`:

```php
<?php

namespace App\Imports;

use App\Enums\StudentGender;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

final class StudentRowValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, SchoolClass>  $kelas
     * @return array<int, array<string, mixed>>
     */
    public function validate(array $rows, Collection $kelas): array
    {
        $nisnDiDatabase = Student::withTrashed()->pluck('nisn')->map(fn ($nisn) => (string) $nisn)->all();

        /** @var array<string, SchoolClass> $kelasPerNama */
        $kelasPerNama = [];

        foreach ($kelas as $item) {
            $kelasPerNama[mb_strtoupper($item->name)] = $item;
        }

        $hasil = [];
        $nisnDiFileIni = [];

        foreach ($rows as $index => $row) {
            $nomor = $index + 2;

            $nisn = $this->nilai($row, ['nisn']);
            $nama = $this->nilai($row, ['nama', 'nama lengkap', 'name']);
            $jk = $this->nilai($row, ['jk', 'jenis kelamin', 'gender', 'lp']);
            $namaKelas = $this->nilai($row, ['kelas', 'class']);

            $galat = [];

            if ($nisn === '') {
                $galat[] = 'NISN wajib diisi.';
            } elseif (in_array($nisn, $nisnDiDatabase, true)) {
                $galat[] = "NISN {$nisn} sudah terdaftar, termasuk yang diarsipkan.";
            } elseif (isset($nisnDiFileIni[$nisn])) {
                $galat[] = "NISN {$nisn} juga dipakai pada baris {$nisnDiFileIni[$nisn]}.";
            }

            if ($nama === '') {
                $galat[] = 'Nama wajib diisi.';
            }

            $gender = $this->gender($jk);
            $labelJk = $jk;

            if ($gender === null) {
                $galat[] = "Jenis kelamin '{$jk}' tidak dikenal. Gunakan L atau P.";
            } else {
                $labelJk = $gender->value;
            }

            $kelasTerpilih = null;
            $labelKelas = $namaKelas;

            if ($namaKelas === '') {
                $galat[] = 'Kelas wajib diisi.';
            } else {
                $kelasTerpilih = $kelasPerNama[mb_strtoupper($namaKelas)] ?? null;

                if ($kelasTerpilih === null) {
                    $galat[] = "Kelas '{$namaKelas}' tidak ditemukan pada tahun ajaran terpilih.";
                } else {
                    $labelKelas = $kelasTerpilih->name;
                }
            }

            $hasil[] = [
                'row' => $nomor,
                'nisn' => $nisn,
                'nama' => $nama,
                'gender' => $labelJk,
                'kelas' => $labelKelas,
                'class_id' => $kelasTerpilih?->id,
                'valid' => $galat === [],
                'errors' => $galat,
            ];

            if ($nisn !== '' && ! in_array($nisn, $nisnDiDatabase, true)) {
                $nisnDiFileIni[$nisn] = $nomor;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $kunci
     */
    private function nilai(array $row, array $kunci): string
    {
        foreach ($row as $kolom => $isi) {
            if (in_array(mb_strtolower(trim((string) $kolom)), $kunci, true)) {
                return trim((string) $isi);
            }
        }

        return '';
    }

    private function gender(string $isi): ?StudentGender
    {
        return match (mb_strtolower($isi)) {
            'l', 'lk', 'pri', 'laki-laki', 'laki laki' => StudentGender::L,
            'p', 'pr', 'perempuan' => StudentGender::P,
            default => null,
        };
    }
}
```

`Student::withTrashed()` dipakai karena index unique `students.nisn` tidak melihat `deleted_at`. Kalau tidak, impor ulang file yang sama setelah arsip akan ditabrak `QueryException` dari database — dan pesan error-nya tidak menyebut kolom mana.

`app/Services/StudentImporter.php`:

```php
<?php

namespace App\Services;

use App\Enums\StudentGender;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StudentImporter
{
    public const UKURAN_CHUNK = 100;

    /**
     * @param  array<int, array<string, mixed>>  $baris
     */
    public function import(array $baris): int
    {
        $potongan = array_chunk($baris, self::UKURAN_CHUNK);

        return (int) DB::transaction(function () use ($potongan) {
            $tersimpan = 0;

            foreach ($potongan as $chunk) {
                $waktu = now();

                Student::query()->insert(array_map(
                    fn (array $baris) => $this->baris($baris, $waktu),
                    $chunk
                ));

                $tersimpan += count($chunk);
            }

            return $tersimpan;
        });
    }

    private function baris(array $baris, Carbon $waktu): array
    {
        $gender = (string) $baris['gender'];

        if (! in_array($gender, array_column(StudentGender::cases(), 'value'), true)) {
            throw ValidationException::withMessages([
                'file' => "Jenis kelamin tidak valid pada baris {$baris['row']}.",
            ]);
        }

        return [
            'id' => (string) Str::uuid(),
            'class_id' => $baris['class_id'],
            'nisn' => $baris['nisn'],
            'name' => $baris['nama'],
            'gender' => $gender,
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ];
    }
}
```

Tiga hal yang disengaja di sini:

1. **`insert()` per chunk 100 baris, bukan `create()` per baris.** 200 siswa berarti 200 query; bulk insert 100 baris berarti dua query. UUID dibuat eksplisit karena `insert()` melewati `HasUuids`. Kolom `deleted_at` dibiarkan `NULL` oleh default kolom, `created_at` dan `updated_at` diisi manual.
2. **Satu `DB::transaction` membungkus semua potongan.** Dari 200 siswa, kalau baris 150 gagal validasi, 149 baris pertama ikut rollback.
3. **Pengecekan ulang `StudentGender` di dalam transaksi.** Validator sudah menjamin, tapi ini satu-satunya penjaga kalau array `$baris` datang dari pemanggil lain di masa depan. Jenis kelamin adalah kolom `NOT NULL` tanpa default; insert nilai yang tidak dikenal akan menjadi `QueryException` yang tidak informatif.

- [ ] **Step 4: Tulis komponen**

`app/Livewire/StudentImport.php`:

```php
<?php

namespace App\Livewire;

use App\Imports\StudentRowValidator;
use App\Imports\StudentSheetReader;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class StudentImport extends Component
{
    use WithFileUploads;

    public string $academic_year_id = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    public ?string $namaFile = null;

    public array $pratinjau = [];

    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'file' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
                'max:5120',
            ],
        ];
    }

    public function mount(): void
    {
        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::query()
                ->where('is_active', true)
                ->value('id') ?? AcademicYear::query()->orderByDesc('start_date')->value('id'));
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function barisGagal(): array
    {
        return array_values(array_filter(
            $this->pratinjau,
            fn (array $baris) => $baris['valid'] === false
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function barisValid(): array
    {
        return array_values(array_filter(
            $this->pratinjau,
            fn (array $baris) => $baris['valid'] === true
        ));
    }

    public function preview(): void
    {
        $this->validate();

        $kelas = SchoolClass::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->orderBy('name')
            ->get();

        $berkas = $this->file;

        if (! $berkas instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak terbaca. Unggah ulang.',
            ]);
        }

        $pembaca = new StudentSheetReader;

        Excel::import($pembaca, $berkas->getRealPath(), null, ExcelType::XLSX);

        $this->pratinjau = app(StudentRowValidator::class)->validate($pembaca->rows(), $kelas);
        $this->namaFile = $berkas->getClientOriginalName();
        $this->file = null;

        $this->resetValidation();
    }

    public function confirm(): void
    {
        $gagal = $this->barisGagal();

        if ($gagal !== []) {
            throw ValidationException::withMessages([
                'file' => count($gagal) . ' baris ditolak. Perbaiki file-nya lalu unggah ulang. Tidak ada siswa yang disimpan sebagian.',
            ]);
        }

        Gate::authorize('create', Student::class);

        $jumlah = app(StudentImporter::class)->import($this->barisValid());

        session()->flash('sukses', $jumlah . ' siswa berhasil diimpor.');

        $this->pratinjau = [];
        $this->namaFile = null;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->pratinjau = [];
        $this->namaFile = null;
        $this->file = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('create', Student::class);

        return view('livewire.student-import', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'jumlahValid' => count($this->barisValid()),
            'jumlahGagal' => count($this->barisGagal()),
        ]);
    }
}
```

`$this->file` dikosongkan setelah pratinjau supaya file-nya tidak dikirim ulang pada setiap request berikutnya. Unggahan disimpan sebagai file sementara di disk, dan membiarkan file basi menempel selama berjam-jam di shared hosting itu borongan file 5 MB per admin.

- [ ] **Step 5: Tulis view**

`resources/views/livewire/student-import.blade.php`:

```blade
<x-layouts.app title="Impor Siswa">

    <div class="space-y-6">
        <h1 class="text-lg font-semibold text-gray-900">Impor Siswa dari Excel</h1>

        @if (session('sukses'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif

        <form wire:submit="preview" class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4">
            <label class="block text-sm">
                <span class="text-gray-700">Tahun Ajaran</span>
                <select wire:model="academic_year_id" class="mt-1 w-full rounded border-gray-300">
                    @foreach ($tahunAjaran as $tahun)
                        <option value="{{ $tahun->id }}">{{ $tahun->name }} ({{ ucfirst($tahun->period->value) }})</option>
                    @endforeach
                </select>
                @error('academic_year_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm md:col-span-2">
                <span class="text-gray-700">File Excel (.xlsx, maksimal 5 MB)</span>
                <input type="file" wire:model="file" accept=".xlsx"
                    class="mt-1 w-full rounded border-gray-300" />
                @error('file') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-gray-900 px-3 py-2 text-sm text-white">Periksa</button>
                <button type="button" wire:click="resetForm" class="rounded border px-3 py-2 text-sm">Reset</button>
            </div>
        </form>

        <p class="text-xs text-gray-500">
            Baris 1 harus berisi judul kolom: NISN, Nama, JK, Kelas. Kolom "Kelas" diisi nama kelas
            apa adanya, misalnya VII-A, dan kelas harus ada pada tahun ajaran yang dipilih di atas.
        </p>

        @if ($pratinjau !== [])
            <div class="rounded-lg border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 text-sm">
                    <span class="font-medium">{{ $namaFile }}</span>
                    <span>
                        <span class="text-green-700">{{ $jumlahValid }} akan disimpan</span>
                        <span class="ml-2 text-red-700">{{ $jumlahGagal }} ditolak</span>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Baris</th>
                                <th class="px-4 py-2">NISN</th>
                                <th class="px-4 py-2">Nama</th>
                                <th class="px-4 py-2">L/P</th>
                                <th class="px-4 py-2">Kelas</th>
                                <th class="px-4 py-2">Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pratinjau as $baris)
                                <tr class="border-t border-gray-100 {{ $baris['valid'] ? '' : 'bg-red-50' }}">
                                    <td class="px-4 py-2">{{ $baris['row'] }}</td>
                                    <td class="px-4 py-2">{{ $baris['nisn'] }}</td>
                                    <td class="px-4 py-2">{{ $baris['nama'] }}</td>
                                    <td class="px-4 py-2">{{ $baris['gender'] }}</td>
                                    <td class="px-4 py-2">{{ $baris['kelas'] }}</td>
                                    <td class="px-4 py-2">
                                        @if ($baris['valid'])
                                            <span class="text-green-700">Akan disimpan</span>
                                        @else
                                            <ul class="list-disc pl-4 text-red-700">
                                                @foreach ($baris['errors'] as $pesan)
                                                    <li>{{ $pesan }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center gap-3 border-t border-gray-200 px-4 py-3">
                    <button wire:click="confirm" @disabled($jumlahGagal > 0 || $jumlahValid === 0)
                        class="rounded bg-gray-900 px-3 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-40">
                        Simpan {{ $jumlahValid }} siswa
                    </button>

                    @if ($jumlahGagal > 0)
                        <span class="text-sm text-red-700">
                            Perbaiki {{ $jumlahGagal }} baris yang ditolak, lalu unggah ulang. Tidak ada yang disimpan sebagian.
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=StudentImportTest
```

**Expected: 11 tests pass** (7 test di Task 6 + 4 test tambahan: NISN kembar di dalam file yang sama, NISN siswa terarsip, ejaan kolom dan jenis kelamin berupa kata, kelas dari tahun ajaran lain). Angka 11 sudah diverifikasi dengan `php artisan test --filter=StudentImportTest` di branch `feat/plan-3-data-master`.

Kalau `test_baris_tidak_valid_menampilkan_alasan_dan_tidak_menyimpan_apa_pun` gagal dengan `Student::count()` 1, `confirm()` berjalan padahal ada baris ditolak — pastikan guard `barisGagal()` ada **sebelum** `Gate::authorize` dan sebelum importer dipanggil.

- [ ] **Step 7: Commit**

```powershell
git add -A
git commit -m "feat: impor Excel siswa dengan pratinjau dan simpan penuh atau gagal total"
```

---

---

## Task 7: Ringkasan test dan verifikasi akhir

**Files:**
- Tidak ada file baru. Task ini tidak menambah test; dia menjalankan seluruh yang sudah ditulis di Task 0-6 dan memeriksa hasilnya secara manual.

**Consumes:** semua test dan kode Task 0-6
**Produces:** bukti bahwa plan ini utuh dan tidak merusak apa pun dari Plan 1 dan Plan 2.

- [ ] **Step 1: Jalankan seluruh suite**

```powershell
php artisan test
```

Expected: semua lulus, 0 gagal, 0 skipped.

Subtotal test yang ditulis plan ini:

| Task | File test | Jumlah |
|---|---|---|
| Task 0 | `Policy/DataMasterPolicyTest.php` | 6 |
| Task 1 | `Livewire/AcademicYearManagementTest.php` | 8 |
| Task 2 | `Livewire/SubjectManagementTest.php` | 10 |
| Task 3 | `Livewire/ClassManagementTest.php` | 13 |
| Task 4 | `Livewire/ScheduleManagementTest.php` | 11 |
| Task 5 | `Livewire/StudentManagementTest.php` | 10 |
| Task 6 | `Livewire/StudentImportTest.php` | 11 |
| **Total** | | **69** |

Seluruh angka di tabel ini sudah diverifikasi satu per satu dengan `php artisan test --filter=<file>` di branch `feat/plan-3-data-master`; angka di "Expected" Step 1-6 yang lebih kecil sudah usang. Suite penuh: **232 test, 600 assertion, 0 gagal, 0 skipped**.

- [ ] **Step 2: Pastikan test Plan 1 dan Plan 2 tidak bocor**

```powershell
php artisan test --filter=PolicyTest
php artisan test --filter=SchemaIntegrityTest
php artisan test --filter=DemoSchoolSeederTest
```

Expected: `PolicyTest` 26 lulus (20 Plan 1 + 6 Task 0), `SchemaIntegrityTest` 8 lulus, `DemoSchoolSeederTest` 7 lulus. Kalau salah satu gagal, plan ini mengubah sesuatu yang seharusnya tidak disentuh — periksa `SchoolClassPolicy::viewRecap`, `SchedulePolicy::recordAttendance`, dan `StudentPolicy`.

Tiga angka itu sudah diverifikasi di branch `feat/plan-3-data-master`.

- [ ] **Step 3: Periksa route**

```powershell
php artisan route:list --path=master
```

Expected: 6 route, semua middleware `web` + `auth`:

| Method | URI | Nama |
|---|---|---|
| GET | `master/tahun-ajaran` | `master.tahun-ajaran` |
| GET | `master/mapel` | `master.mapel` |
| GET | `master/kelas` | `master.kelas` |
| GET | `master/jadwal` | `master.jadwal` |
| GET | `master/siswa` | `master.siswa` |
| GET | `master/siswa/impor` | `master.siswa.impor` |

- [ ] **Step 4: Smoke test manual dengan seeder demo**

```powershell
php artisan migrate:fresh --seed
php artisan serve
```

Login memakai NUPTK admin dari `DemoSchoolSeeder` (`100000000000000001`, password `password`), lalu cek satu per satu:

| Layar | Yang dipegang |
|---|---|
| `/master/tahun-ajaran` | Daftar 1 tahun ajaran dari seeder. Tambah tahun ajaran 2026/2027, aktifkan, lalu pastikan tahun lama jadi nonaktif dan badge "Aktif" pindah. Coba tambah tahun dengan rentang tanggal yang menabrak yang ada — harus muncul pesan merah, bukan tabel kosong. |
| `/master/mapel` | 8 mapel dari seeder. Tambah mapel baru, arsipkan satu, lalu checklist "Tampilkan yang diarsipkan" dan pastikan mapel muncul kembali. |
| `/master/kelas` | 7 kelas dengan wali kelas. Buat kelas tanpa wali (pilih "Belum ditentukan") dan pastikan kolom wali menampilkan "Belum ditentukan", bukan kosong atau error. Coba kelas "VII-A" lagi di tahun ajaran yang sama — harus ditolak. |
| `/master/jadwal` | 105 jadwal dari seeder, tergroup per hari. Tambahkan jadwal guru yang sama di jam yang sama — pesan bentrok harus menyebut kelas, mapel, hari, dan jam yang bentrok. Lihat daftar harus terfilter tahun ajaran aktif saja. |
| `/master/siswa` | 210 siswa, 15 per halaman. Cari "Budi", filter kelas VII-A, filter jenis kelamin P. Arsipkan satu siswa yang sudah punya absensi, pastikan flash message menyebut riwayat absensi tidak hilang, lalu pulihkan. |
| `/master/siswa/impor` | Unggah file dengan 3 baris valid: harus muncul "3 akan disimpan" dan tombol simpan aktif. Unggah file dengan 1 baris cacat: tombol simpan nonaktif, baris merah beserta alasannya, dan `students` tidak bertambah. |

Guru **tidak boleh** bisa membuka keenam halaman. Login sebagai salah satu guru dari seeder dan coba setiap URL di atas: semuanya harus 403.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "chore: verifikasi suite penuh dan route data master"
```

---

## Verifikasi akhir

```powershell
php artisan test
```

Target: **semua lulus, 0 gagal, 0 skipped.** Test yang ditulis plan ini: 69. Suite penuh 232 test, 600 assertion — sudah diverifikasi di branch `feat/plan-3-data-master`.

Cakupan spec — setiap aturan yang dipakai plan ini harus punya test:

| Persyaratan spec | Covered di |
|---|---|
| Hanya admin yang mengubah data master (spec §4 tabel cakupan) | Task 0, `DataMasterPolicyTest`; Task 1-6 test "guru tidak bisa membuka halaman" |
| `classes` wajib punya `academic_year_id`, kelas per tahun ajaran (D2) | Task 3, `ClassManagementTest` |
| Unique `('academic_year_id', 'name')` tidak di-bypass | Task 3, `test_nama_kelas_duplikat_di_tahun_ajaran_yang_sama_ditolak` |
| Nama kelas boleh sama di tahun ajaran berbeda (D2) | Task 3, `test_nama_kelas_sama_boleh_di_tahun_ajaran_berbeda` |
| `homeroom_teacher_id` nullable + empty state | Task 3, `test_wali_kelas_bisa_kosong` dan view |
| Kelas diarsipkan, riwayat absensi utuh | Task 3, `test_kelas_berisi_absensi_hanya_diarsipkan` |
| Wali kelas satu per tahun ajaran | Task 3, `test_satu_guru_hanya_bisa_menjadi_wali_satu_kelas_per_tahun` |
| `day_of_week` tinyint 1-5, bukan string (D14) | Task 4, `test_hari_di_luar_senin_jumat_ditolak` |
| Validasi level aplikasi: guru tidak boleh 2 jadwal jam sama | Task 4, `test_jadwal_bentrok_ditolak`, `test_pesan_bentrok_menyebut_kelas_dan_jam` |
| Soft delete jadwal membebaskan slot | Task 4, `test_arsipkan_jadwal_membebaskan_slot` |
| `students.nisn` unique, tidak di-bypass | Task 5, `test_nisn_duplikat_ditolak`; Task 6, `test_nisn_yang_sudah_terdaftar_ditolak` |
| Pindah kelas = update, bukan baris baru | Task 5, `test_siswa_naik_kelas_menyalin_nisn_tetap_valid` |
| Soft delete siswa menjaga histori absensi | Task 5, `test_siswa_diarsipkan_riwayat_absensinya_tetap_utuh` |
| Import Excel siswa ada di v1 (D11) | Task 6, `test_file_valid_mengimpor_seluruh_siswa` |
| Import menampilkan baris ditolak beserta alasannya | Task 6, `test_baris_tidak_valid_menampilkan_alasan_dan_tidak_menyimpan_apa_pun` |
| Import tidak menyimpan sebagian | Task 6, `test_file_sebagian_valid_ditolak_tidak_disimpan_sebagian` |
| Import satu transaksi | Task 6, `StudentImporter` satu `DB::transaction` + test di atas |
| `gender` wajib L/P (D13) | Task 5, `test_kelas_dan_jenis_kelamin_wajib_diisi`; Task 6 validator |
| Tidak ada scheduled task, tidak ada antrean | Plan ini tidak memakai `ShouldQueue`; `QUEUE_CONNECTION=sync` |

Tidak ada aturan spec yang dipakai plan ini tanpa test.

---

## Di luar lingkup plan ini

| Item | Alasan | Masuk |
|---|---|---|
| Template Excel untuk diunduh admin | Butuh kelas export baru; file tata usaha sudah punya format sendiri dan header file sudah toleran | Plan 5 (bila dipakai operator) |
| Edit massal siswa (pindah 30 siswa kelas sekaligus) | Satu file Excel dengan 210 siswa membuat edit massal jarang dipakai; filter kelas sudah menutup kasus umum | Tidak dijadwalkan |
| Hapus jadwal dalam rentang tanggal (misal libur sekolah) | Butuh tabel kalender sekolah yang belum ada di skema | Tidak dijadwalkan untuk v1 |
| Ekspor daftar siswa ke Excel | `RecapExporter` Plan 1 sudah pola yang sama, tapi tidak ada kebutuhan laporan siswa di PRD §4.1 | Plan 5 bila dibutuhkan |
| Import meng-update siswa yang sudah ada | Butuh kolom penanda "NISN lama" di file; sekarang NISN yang sudah ada selalu ditolak supaya tidak ada penimpaan diam-diam | Tidak dijadwalkan |
| Login orang tua, notifikasi WA, QR/face recognition | Di luar scope v1 (spec §10) | Tidak dijadwalkan |
