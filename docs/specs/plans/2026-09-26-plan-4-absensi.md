# Plan 4 — Absensi

> **Untuk agent yang mengerjakan:** WAJIB memakai skill `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan plan ini task per task. Format `- [ ]` dipakai untuk melacak progres.

**Goal:** Membangun inti produk — halaman "Jadwal Hari Ini" dan form input absensi per jam pelajaran — sehingga guru bisa mencatat kehadiran siswa dengan benar, dan semua tulisan database tetap melewati satu-satunya pintu `AttendanceRecorder`.

**Architecture:** Dua Livewire full-page component: `TodaySchedule` (daftar jadwal hari milik pengguna) dan `AttendanceEntry` (form absensi per jadwal). Scoping jadwal hari ini di `TodaySchedulesQuery` yang bertanya ke `SchedulePolicy`, bukan menulis kondisi role. Form tidak pernah menyentuh query builder untuk menulis: ia menyusun `Collection` baris lalu memanggil `AttendanceRecorder::record()` yang sudah menguji `recordAttendance`/`update`, memvalidasi keanggotaan kelas, dan membungkus seluruh batch dalam satu transaksi. Tanggal terkunci dihitung dari Carbon pada `APP_TIMEZONE=Asia/Jakarta` — tidak pernah dari browser.

**Spec:** `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`

---

## Global Constraints

Nilai-nilai ini berasal dari spec §3-§5 dan Plan 1, dan berlaku untuk **setiap task**. Jangan menyimpang tanpa keputusan manusia.

- **Gate versi sudah diputuskan di Plan 1 Task 1: PHP 8.3, Laravel `^13.0`.** Tidak ada fallback ke PHP 8.2 / Laravel 12 (D16). Plan ini memakai apa adanya hasil gate itu, tidak menentukan ulang.

- **`APP_TIMEZONE=Asia/Jakarta` wajib.** Semua `now()`, `today()`, dan `DayOfWeek` diturunkan dari situ. Default UTC bisa menggeser `date('Y-m-d')` sehari pada absensi jam 7 pagi.
- **Hari ini tidak pernah datang dari browser.** `day_of_week` dicocokkan dari `Carbon::now()->dayOfWeekIso`, bukan dari jam HP guru.
- **`QUEUE_CONNECTION=sync`.** Tidak ada `queue:work` di shared hosting.
- **Primary key UUID** di semua tabel (trait `HasUuids`).
- **Wali kelas bukan role.** `role` hanya berisi `admin` atau `guru`; status wali kelas dibaca dari `classes.homeroom_teacher_id`.
- **Scoping hanya di Policy dan Query Object.** Dilarang keras menulis kondisi role di dalam Blade atau method `render()` component. Itu bug, bukan fitur.
- **Soft delete** di `users`, `students`, `classes`, `subjects`, `schedules`. **Tidak** di `attendances` dan `academic_years`.
- **`attendances` tidak pernah dihapus.** Koreksi selalu lewat ubah `status` + isi `updated_by` (dicatat `AttendanceRecorder`, bukan form).
- **Unique constraint tidak boleh di-bypass.** `attendances` unique `(student_id, schedule_id, `date`)`; component tidak boleh menebaknya sendiri lewat `insert`.
- **Penulisan database lewat `AttendanceRecorder::record(Schedule $schedule, Carbon $date, Collection $rows, User $actor): int`.** Component tidak pernah memanggil `Attendance::create()` atau `update()`.
- **Tidak ada auto-save** (spec §5.2). Klik salah di HP guru bisa menimpa absensi yang sudah benar.
- **Test memakai SQLite in-memory** lewat `phpunit.xml` Plan 1 Task 1. `.env` lokal tidak pernah disentuh test.
- **Test waktu selalu dipatok** dengan `Carbon::setTestNow()` supaya hari dan tanggalnya deterministik.
- **Semua pesan, label, dan exception dalam bahasa Indonesia.**
- **Tidak ada kode "perbaiki nanti".** Setiap snippet adalah kode final yang langsung ditulis ke disk.

---

## File Structure

Semua file yang dibuat atau diubah plan ini:

```
app/
  Attendance/
    Queries/
      TodaySchedulesQuery.php     # jadwal milik pengguna per hari, lewat SchedulePolicy::view
      AdminContactsQuery.php      # tujuan "Hubungi admin" saat absensi terkunci
  Support/
    IndonesianDate.php            # nama hari + bulan Bahasa Indonesia, tanpa bergantung locale
  Livewire/
    TodaySchedule.php             # daftar jadwal hari ini + penanda sudah/belum absen
    AttendanceEntry.php           # form absensi per jadwal — komponen inti
  Http/Middleware/
  Http/Middleware/                            # BUATAN PLAN 2, dipakai di sini
resources/views/livewire/
  today-schedule.blade.php
  attendance-entry.blade.php
tests/Feature/Livewire/
  TodayScheduleTest.php
  AttendanceEntryAccessTest.php
  AttendanceEntryLoadTest.php
  AttendanceEntryFormTest.php
  AttendanceEntrySaveTest.php
  AttendanceEntryWindowTest.php
  AbsensiRoutesTest.php
```

**Tidak diubah di plan ini:** `routes/web.php` hanya disisipkan blok rute, `bootstrap/app.php` tidak disentuh (middleware dipanggil sebagai kelas, bukan alias), dan tidak ada migration baru.

---

## Task 1: `TodaySchedule` — daftar jadwal hari ini

**Files:**
- Create: `app/Attendance/Queries/TodaySchedulesQuery.php`
- Create: `app/Attendance/Queries/AdminContactsQuery.php`
- Create: `app/Support/IndonesianDate.php`
- Create: `app/Livewire/TodaySchedule.php`
- Create: `resources/views/livewire/today-schedule.blade.php`
- Modify: `routes/web.php` (sisipkan blok rute di dalam grup `auth`)
- Test: `tests/Feature/Livewire/TodayScheduleTest.php`

**Consumes:** `Schedule`, `SchoolClass`, `Attendance`, `User` + `SchedulePolicy` dari Plan 1 Task 3-4
**Produces:** rute `jadwal.index` dan `absensi.create`/`absensi.show`, plus `TodaySchedulesQuery` yang jadi satu-satunya sumber daftar jadwal hari ini

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/TodayScheduleTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Livewire\TodaySchedule;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const SENIN = '2026-09-28';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::SENIN, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function masukSebagaiGuru(): User
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru);

        return $guru;
    }

    public function test_menampilkan_jadwal_hari_ini_dengan_jumlah_siswa(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);
        Student::factory()->count(3)->create(['class_id' => $jadwal->class_id]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Senin, 28 September 2026')
            ->assertSee('07:00 - 08:30')
            ->assertSee($jadwal->subject->name)
            ->assertSee('Kelas ' . $jadwal->schoolClass->name . ' - 3 siswa')
            ->assertSee('Belum absen');
    }

    public function test_jadwal_guru_lain_tidak_muncul(): void
    {
        $this->masukSebagaiGuru();
        Schedule::factory()->create([
            'teacher_id' => User::factory()->guru()->create()->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }

    public function test_jadwal_hari_lain_tidak_muncul(): void
    {
        $guru = $this->masukSebagaiGuru();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Selasa,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }

    public function test_mengurutkan_jadwal_menurut_jam_mulai(): void
    {
        $guru = $this->masukSebagaiGuru();

        foreach ([
            ['10:15:00', '11:45:00'],
            ['07:00:00', '08:30:00'],
            ['09:00:00', '10:00:00'],
        ] as [$mulai, $selesai]) {
            Schedule::factory()->create([
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::Senin,
                'start_time' => $mulai,
                'end_time' => $selesai,
            ]);
        }

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSeeInOrder(['07:00 - 08:30', '09:00 - 10:00', '10:15 - 11:45']);
    }

    public function test_status_sudah_absen_muncul_setelah_baris_terisi(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Belum absen');

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Sudah absen')
            ->assertDontSee('Belum absen');
    }

    public function test_hari_sabtu_menampilkan_empty_state(): void
    {
        $this->masukSebagaiGuru();
        Carbon::setTestNow(Carbon::parse('2026-09-26', 'Asia/Jakarta'));

        Schedule::factory()->create(['day_of_week' => DayOfWeek::Senin]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Sabtu, 26 September 2026')
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=TodayScheduleTest
```

Expected: FAIL — `Class "App\Livewire\TodaySchedule" not found`.

- [ ] **Step 3: Tulis implementasi**

`app/Support/IndonesianDate.php` — nama hari dan bulan Bahasa Indonesia tanpa bergantung `ext-intl` atau locale Carbon:

```php
<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class IndonesianDate
{
    private const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function tanggal(Carbon $date): string
    {
        return sprintf(
            '%s, %d %s %d',
            self::HARI[(int) $date->dayOfWeekIso],
            (int) $date->day,
            self::BULAN[(int) $date->month],
            (int) $date->year,
        );
    }

    public static function jam(Carbon $waktu): string
    {
        return $waktu->format('H:i');
    }
}
```

`IndonesianDate` punya 7 nama hari, sedangkan enum `DayOfWeek` hanya 1-5. Enum itu hanya untuk mencocokkan kolom `schedules.day_of_week`; label tanggal butuh akhir pekan juga.

`app/Attendance/Queries/TodaySchedulesQuery.php`:

```php
<?php

namespace App\Attendance\Queries;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal milik satu pengguna untuk satu tanggal.
 *
 * Kandidat dipersempit dengan `teacher_id` supaya query tidak menarik
 * jadwal seluruh sekolah, lalu setiap kandidat tetap ditanyakan ke
 * SchedulePolicy sebagai satu-satunya sumber kebenaran. Tidak ada
 * kondisi role di kelas ini maupun di view.
 */
final class TodaySchedulesQuery
{
    /**
     * @return Collection<int,Schedule>
     */
    public function for(User $user, Carbon $tanggal): Collection
    {
        $hari = DayOfWeek::tryFrom((int) $tanggal->startOfDay()->dayOfWeekIso);

        if ($hari === null) {
            return new Collection;
        }

        $policy = app(SchedulePolicy::class);

        return Schedule::query()
            ->with(['schoolClass', 'subject'])
            ->where('teacher_id', $user->id)
            ->where('day_of_week', $hari)
            ->orderBy('start_time')
            ->get()
            ->reject(fn (Schedule $schedule) => ! $policy->view($user, $schedule))
            ->values();
    }
}
```

`DayOfWeek::tryFrom()` untuk hari Sabtu dan Minggu menghasilkan `null`, jadi hasilnya collection kosong — bukan exception. Sekolah hanya beroperasi Senin-Jumat ([D14]), dan hari libur harus jatuh ke empty state, bukan ke halaman error.

`app/Attendance/Queries/AdminContactsQuery.php`:

```php
<?php

namespace App\Attendance\Queries;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Akun admin aktif, ditampilkan sebagai tujuan "Hubungi admin" ketika
 * absensi tidak bisa diubah. Syarat role tinggal di sini, bukan di view.
 */
final class AdminContactsQuery
{
    /**
     * @return Collection<int,array{nama:string,email:?string}>
     */
    public function active(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'email'])
            ->map(fn (User $admin) => [
                'nama' => $admin->name,
                'email' => $admin->email,
            ]);
    }
}
```

`app/Livewire/TodaySchedule.php`:

```php
<?php

namespace App\Livewire;

use App\Attendance\Queries\TodaySchedulesQuery;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Jadwal Hari Ini')]
class TodaySchedule extends Component
{
    public function render()
    {
        $tanggal = now()->startOfDay();
        $jadwal = app(TodaySchedulesQuery::class)->for(auth()->user(), $tanggal);

        $jumlahSiswa = SchoolClass::query()
            ->withCount('students')
            ->whereIn('id', $jadwal->pluck('class_id')->unique())
            ->pluck('students_count', 'id');

        $tercatat = Attendance::query()
            ->whereIn('schedule_id', $jadwal->pluck('id'))
            ->whereDate('date', $tanggal->toDateString())
            ->selectRaw('schedule_id, count(*) as total')
            ->groupBy('schedule_id')
            ->pluck('total', 'schedule_id');

        $baris = $jadwal->map(fn (Schedule $item) => [
            'jadwal' => $item,
            'jumlahSiswa' => (int) ($jumlahSiswa[$item->class_id] ?? 0),
            'sudahAbsen' => (int) ($tercatat[$item->id] ?? 0) > 0,
        ]);

        return view('livewire.today-schedule', [
            'tanggal' => $tanggal,
            'baris' => $baris,
        ]);
    }
}
```

Tiga query untuk seluruh halaman, bukan satu per baris: satu untuk jadwal, satu agregat `students` per kelas, satu agregat `attendances` per jadwal. Penanda "Sudah absen" adalah **perbandingan jumlah baris** dari query agregat itu — bukan loop yang memanggil absensi per jadwal.

`resources/views/livewire/today-schedule.blade.php`:

```blade
<div class="py-12">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-800">Jadwal Mengajar Hari Ini</h1>
            <p class="mt-1 text-sm text-zinc-600">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if ($baris->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-10 text-center">
                <p class="text-base font-medium text-zinc-800">Tidak ada jadwal mengajar hari ini.</p>
                <p class="mt-1 text-sm text-zinc-500">Cek lagi besok, atau hubungi admin bila jadwal kamu belum terdaftar.</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($baris as $baris)
                    <li wire:key="jadwal-{{ $baris['jadwal']->id }}"
                        class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-2xl font-bold text-zinc-900">
                                    {{ \App\Support\IndonesianDate::jam($baris['jadwal']->start_time) }} - {{ \App\Support\IndonesianDate::jam($baris['jadwal']->end_time) }}
                                </p>
                                <p class="mt-1 text-base font-semibold text-zinc-800">{{ $baris['jadwal']->subject->name }}</p>
                                <p class="text-sm text-zinc-600">
                                    Kelas {{ $baris['jadwal']->schoolClass->name }} - {{ $baris['jumlahSiswa'] }} siswa
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-3">
                                @if ($baris['sudahAbsen'])
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Sudah absen</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Belum absen</span>
                                @endif
                                <a href="{{ route('absensi.create', $baris['jadwal']) }}"
                                   class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                                    {{ $baris['sudahAbsen'] ? 'Perbarui Absensi' : 'Isi Absensi' }}
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
```

View ini **tidak** memuat satu pun kondisi role. Badge "Sudah absen" datang dari flag yang sudah dihitung di component, dan rute diambil lewat `route()`.

`routes/web.php` — sisipkan `use` berikut di bagian atas file:

```php
use App\Livewire\AttendanceEntry;
use App\Livewire\TodaySchedule;
```

Lalu sisipkan tiga baris ini **di dalam** grup `Route::middleware('auth')->group(...)` yang sudah dibuat Plan 2:

```php
    Route::get('/jadwal', TodaySchedule::class)->name('jadwal.index');
    Route::get('/absensi/{schedule}', AttendanceEntry::class)->name('absensi.create');
    Route::get('/absensi/{schedule}/{tanggal}', AttendanceEntry::class)->name('absensi.show');
```

Rute `/absensi/{schedule}/{tanggal}` hanya dipakai admin untuk membuka tanggal lampau. Guru tetap sampai ke sana lewat `/absensi/{schedule}` yang memakai hari ini. `AttendanceEntry` baru bisa didispatch setelah Task 3 — task ini mendaftarkan rute saja, dan `route('absensi.create')` hanya butuh nama rute terdaftar, bukan kelasnya.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=TodayScheduleTest
```

Expected: 6 tests pass (6 test baru).

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: TodaySchedule dengan scoping lewat SchedulePolicy dan tiga query agregat"
```

---

## Task 2: Route dan proteksi halaman absensi

**Files:**
- Create: `app/Livewire/AttendanceEntry.php` (kerangka: mount + gate)
- Create: `resources/views/livewire/attendance-entry.blade.php` (kerangka)
- Modify: `routes/web.php` (ganti blok rute dari Task 1 dengan versi final)
- Test: `tests/Feature/Livewire/AttendanceEntryAccessTest.php`

**Consumes:** `SchedulePolicy` Plan 1 Task 4, `Schedule` + `User` Plan 1 Task 3, `EnsureUserIsActive` Plan 2 Task 2
**Produces:** `AttendanceEntry` yang menolak 403 untuk jadwal orang lain, tanggal di luar jendela, dan akun nonaktif

Task ini membuat kerangka component supaya proteksi rutenya bisa diuji lebih dulu. Form, interaksi, dan penyimpanan menyusul di Task 3-6.

`EnsureUserIsActive` **dimakai, bukan dibuat ulang di sini.** Middleware itu milik Plan 2 karena berkaitan dengan sesi login, bukan absensi. Kalau plan ini membuat salinannya sendiri, akan ada dua middleware dengan nama sama yang berbeda perilaku — dan yang salah adalah yang diam-diam tidak pernah dipanggil.

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AttendanceEntryAccessTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guru_pengajar_bisa_membuka_halaman_absensi(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->actingAs($guru)
            ->get(route('absensi.create', $jadwal))
            ->assertOk();
    }

    public function test_guru_mengajar_jadwal_lain_mendapat_403(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwalLain = Schedule::factory()->create();

        $this->actingAs($guru)
            ->get(route('absensi.create', $jadwalLain))
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_membuka_tanggal_di_luar_jendela(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->actingAs($guru)
            ->get(route('absensi.show', [$jadwal, today()->subDays(3)->toDateString()]))
            ->assertForbidden();
    }

    public function test_akun_tidak_aktif_mendapat_403(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => false]);
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->actingAs($guru)
            ->get(route('absensi.create', $jadwal))
            ->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_absensi_jadwal_siapa_pun(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();

        $this->actingAs($admin)
            ->get(route('absensi.create', $jadwal))
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AttendanceEntryAccessTest
```

Expected: FAIL — `Class "App\Livewire\AttendanceEntry" not found`.

- [ ] **Step 3: Tulis implementasi**

`app/Livewire/AttendanceEntry.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    public Carbon $tanggal;

    public function mount(Schedule $schedule, ?string $tanggal = null): void
    {
        $this->schedule = $schedule;
        $this->tanggal = $this->tanggalDari($tanggal);

        Gate::authorize('view', $this->schedule);
        Gate::authorize('recordAttendance', [$this->schedule, $this->tanggal]);
    }

    /**
     * Tanggal terkunci. Nilai dari rute adalah satu-satunya sumber selain
     * hari ini, dan bentuknya harus persis Y-m-d supaya tidak ada tanggal
     * liar yang lolos ke query.
     */
    private function tanggalDari(?string $tanggal): Carbon
    {
        if ($tanggal === null) {
            return now()->startOfDay();
        }

        if (! Carbon::hasFormat($tanggal, 'Y-m-d')) {
            abort(404);
        }

        return Carbon::parse($tanggal, 'Asia/Jakarta')->startOfDay();
    }

    public function render()
    {
        return view('livewire.attendance-entry', [
            'tanggal' => $this->tanggal,
        ]);
    }
}
```

`resources/views/livewire/attendance-entry.blade.php`:

```blade
<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-zinc-800">Input Absensi</h1>
        <p class="text-sm text-zinc-600">
            {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}
        </p>
    </div>
</div>
```

`routes/web.php` — ganti blok rute dari Task 1 dengan blok final berikut, tetap di dalam grup `Route::middleware('auth')->group(...)`:

```php
    Route::get('/jadwal', TodaySchedule::class)->name('jadwal.index');

    Route::get('/absensi/{schedule}', AttendanceEntry::class)
        ->middleware(EnsureUserIsActive::class)
        ->name('absensi.create');

    Route::get('/absensi/{schedule}/{tanggal}', AttendanceEntry::class)
        ->middleware(EnsureUserIsActive::class)
        ->name('absensi.show');
```

Tambahkan `use` berikut di bagian atas `routes/web.php`:

```php
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\AttendanceEntry;
use App\Livewire\TodaySchedule;
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AttendanceEntryAccessTest
```

Expected: 5 tests pass (5 test baru).

Untuk sekarang tanggal di luar jendela menghasilkan 403. Task 6 mengubahnya menjadi pesan berbahasa manusia, karena guru yang absensinya sudah lewat 1 hari perlu tahu harus menghubungi siapa — 403 tanpa konteks tidak membantu.

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: rute absensi terlindungi SchedulePolicy dan middleware akun aktif"
```

---

## Task 3: `AttendanceEntry` memuat form

**Files:**
- Modify: `app/Livewire/AttendanceEntry.php`
- Modify: `resources/views/livewire/attendance-entry.blade.php`
- Test: `tests/Feature/Livewire/AttendanceEntryLoadTest.php`

**Consumes:** `Attendance`, `Student` + factory Plan 1 Task 3
**Produces:** form yang menampilkan tanggal terkunci, jam jadwal besar, seluruh siswa default "hadir", dan data absensi lama kalau sudah pernah diisi

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AttendanceEntryLoadTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function jadwalDenganSiswa(int $jumlahSiswa = 3): array
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $jadwal->class_id,
                'name' => 'Siswa ' . $urutan,
            ]);
        }

        $this->actingAs($guru);

        return [$guru, $jadwal];
    }

    public function test_tanggal_terkunci_ke_hari_ini(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Tanggal absensi dikunci')
            ->assertSee('Senin, 28 September 2026')
            ->assertSet('tanggal', today());
    }

    public function test_tidak_ada_input_tanggal_yang_bisa_diubah(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertDontSee('type="date"', false)
            ->assertDontSee('name="tanggal"', false);
    }

    public function test_menampilkan_jam_jadwal_besar_di_atas_form(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('07:00 - 08:30')
            ->assertSee($jadwal->subject->name)
            ->assertSee('Kelas ' . $jadwal->schoolClass->name);
    }

    public function test_semua_siswa_default_hadir_dan_terurut_nama(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()
            ->where('class_id', $jadwal->class_id)
            ->orderBy('name')
            ->pluck('id');

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSeeInOrder(['Siswa 1', 'Siswa 2', 'Siswa 3']);

        foreach ($siswa as $id) {
            $component->assertSet('status.' . $id, 'hadir');
        }
    }

    public function test_absensi_lama_dimuat_bukan_form_kosong(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Sakit,
            'note' => 'Demam sejak pagi',
            'recorded_by' => $guru->id,
        ]);

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSet('sudahDisimpan', true)
            ->assertSet('status.' . $siswa->id, 'sakit')
            ->assertSet('note.' . $siswa->id, 'Demam sejak pagi');
    }

    public function test_tombol_menjadi_simpan_perubahan_setelah_absensi_ada(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Absensi')
            ->assertDontSee('Simpan Perubahan');

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('Simpan Absensi');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AttendanceEntryLoadTest
```

Expected: FAIL — `assertSet('sudahDisimpan', true)` gagal karena `mount()` belum memuat absensi lama.

- [ ] **Step 3: Tulis implementasi**

`app/Livewire/AttendanceEntry.php` — ganti seluruh isi:

```php
<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    public Carbon $tanggal;

    public bool $sudahDisimpan = false;

    /**
     * @var array<string,array{nama:string,nisn:string,jk:string}>
     */
    public array $baris = [];

    /**
     * @var array<string,string>
     */
    public array $status = [];

    /**
     * @var array<string,string|null>
     */
    public array $note = [];

    public function mount(Schedule $schedule, ?string $tanggal = null): void
    {
        $this->schedule = $schedule;
        $this->tanggal = $this->tanggalDari($tanggal);

        Gate::authorize('view', $this->schedule);
        Gate::authorize('recordAttendance', [$this->schedule, $this->tanggal]);

        $tersimpan = Attendance::query()
            ->where('schedule_id', $this->schedule->id)
            ->whereDate('date', $this->tanggal->toDateString())
            ->get(['student_id', 'status', 'note'])
            ->keyBy('student_id');

        $this->sudahDisimpan = $tersimpan->isNotEmpty();

        $siswa = $this->schedule->schoolClass->students()->orderBy('name')->get();

        foreach ($siswa as $orang) {
            $baris = $tersimpan->get($orang->id);

            $this->baris[$orang->id] = [
                'nama' => $orang->name,
                'nisn' => $orang->nisn,
                'jk' => $orang->gender->value,
            ];

            $this->status[$orang->id] = $baris?->status?->value ?? AttendanceStatus::Hadir->value;
            $this->note[$orang->id] = $baris?->note;
        }
    }

    /**
     * Tanggal terkunci. Nilai dari rute adalah satu-satunya sumber selain
     * hari ini, dan bentuknya harus persis Y-m-d supaya tidak ada tanggal
     * liar yang lolos ke query.
     */
    private function tanggalDari(?string $tanggal): Carbon
    {
        if ($tanggal === null) {
            return now()->startOfDay();
        }

        if (! Carbon::hasFormat($tanggal, 'Y-m-d')) {
            abort(404);
        }

        return Carbon::parse($tanggal, 'Asia/Jakarta')->startOfDay();
    }

    public function render()
    {
        return view('livewire.attendance-entry', [
            'tanggal' => $this->tanggal,
        ]);
    }
}
```

`attendances` **tidak** memakai soft delete, jadi baris yang sudah ada tidak pernah disembunyikan. Isi `status` diambil dari cast enum, `note` apa adanya, dan `sudahDisimpan` hanya flag tampilan untuk mengganti label tombol.

`resources/views/livewire/attendance-entry.blade.php` — ganti seluruh isi:

```blade
<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-medium text-zinc-500">
                {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}
            </p>
            <p class="mt-1 text-4xl font-bold tracking-tight text-zinc-900">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Siswa</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($baris as $id => $siswa)
                        <tr wire:key="baris-{{ $id }}">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-zinc-900">{{ $siswa['nama'] }}</p>
                                <p class="text-xs text-zinc-500">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model.live="status.{{ $id }}"
                                        class="rounded-lg border-zinc-300 text-sm text-zinc-900">
                                    @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                        <option value="{{ $opsi->value }}">{{ ucfirst($opsi->value) }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
```

Tidak ada satu pun `<input>` atau `<select>` untuk tanggal. Tanggal ditampilkan sebagai teks hasil hitungan server. Guru tidak punya cara mengetik tanggal lain, dan satu-satunya jalan untuk mengubah tanggal adalah URL — yangpun sudah ditolak `SchedulePolicy::recordAttendance` di Task 2.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AttendanceEntryLoadTest
```

Expected: 6 tests pass (6 test baru).

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: AttendanceEntry memuat siswa, tanggal terkunci, dan absensi lama"
```

---

## Task 4: Interaksi form — default, massal, alasan kondisional

**Files:**
- Modify: `app/Livewire/AttendanceEntry.php`
- Modify: `resources/views/livewire/attendance-entry.blade.php`
- Test: `tests/Feature/Livewire/AttendanceEntryFormTest.php`

**Consumes:** `baris`, `status`, `note` dari Task 3
**Produces:** tombol massal, kolom alasan yang hanya muncul saat status bukan "hadir", dan pembersihan alasan otomatis

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AttendanceEntryFormTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function formSiap(int $jumlahSiswa = 3): array
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $jadwal->class_id,
                'name' => 'Siswa ' . $urutan,
            ]);
        }

        $this->actingAs($guru);

        return [$jadwal, Student::query()->where('class_id', $jadwal->class_id)->pluck('id')];
    }

    public function test_tandai_semua_hadir_mengisi_seluruh_baris(): void
    {
        [$jadwal, $ids] = $this->formSiap();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'izin');

        foreach ($ids as $id) {
            $component->assertSet('status.' . $id, 'izin');
        }

        $component->call('tandaiSemua', 'hadir');

        foreach ($ids as $id) {
            $component->assertSet('status.' . $id, 'hadir');
        }
    }

    public function test_tandai_semua_alpa_mengisi_seluruh_baris(): void
    {
        [$jadwal, $ids] = $this->formSiap();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'alpa');

        foreach ($ids as $id) {
            $component->assertSet('status.' . $id, 'alpa');
        }
    }

    public function test_kolom_alasan_muncul_untuk_izin_sakit_dan_alpa(): void
    {
        [$jadwal, $ids] = $this->formSiap(1);
        $id = $ids->first();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');

        foreach (['izin', 'sakit', 'alpa'] as $status) {
            $component->set('status.' . $id, $status)
                ->assertSee('Contoh: Demam, acara keluarga, dll');
        }

        $component->set('status.' . $id, 'hadir')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');
    }

    public function test_kolom_alasan_hilang_saat_status_kembali_hadir(): void
    {
        [$jadwal, $ids] = $this->formSiap(1);
        $id = $ids->first();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.' . $id, 'sakit')
            ->assertSee('Contoh: Demam, acara keluarga, dll')
            ->set('status.' . $id, 'hadir')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');
    }

    public function test_tidak_ada_autosave_saat_status_diubah(): void
    {
        [$jadwal, $ids] = $this->formSiap(2);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'alpa')
            ->set('note.' . $ids->first(), 'Tidak masuk tanpa keterangan')
            ->assertOk();

        $this->assertSame(0, Attendance::count());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AttendanceEntryFormTest
```

Expected: FAIL — `Method tandaiSemua does not exist`.

- [ ] **Step 3: Tulis implementasi**

`app/Livewire/AttendanceEntry.php` — tambahkan tiga method ini di atas `tanggalDari()`:

```php
    public function tandaiSemua(string $status): void
    {
        $pilihan = AttendanceStatus::from($status);

        foreach (array_keys($this->baris) as $id) {
            $this->status[$id] = $pilihan->value;
        }

        $this->note = [];
    }

    /**
     * Alasan hanya relevan untuk izin, sakit, dan alpa. Saat guru
     * mengembalikan status ke hadir, alasan lama dibuang supaya tidak
     * ikut tersimpan sebagai sisa.
     */
    public function updatedStatus(mixed $value, string $key): void
    {
        if ($value !== AttendanceStatus::Hadir->value) {
            return;
        }

        unset($this->note[$key]);
    }

    public function alasanTerlihat(string $studentId): bool
    {
        return ($this->status[$studentId] ?? AttendanceStatus::Hadir->value)
            !== AttendanceStatus::Hadir->value;
    }
```

`resources/views/livewire/attendance-entry.blade.php` — ganti seluruh isi:

```blade
<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-medium text-zinc-500">
                {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}
            </p>
            <p class="mt-1 text-4xl font-bold tracking-tight text-zinc-900">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="tandaiSemua('hadir')"
                    class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                Tandai Semua Hadir
            </button>
            <button type="button" wire:click="tandaiSemua('alpa')"
                    class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                Tandai Semua Alpa
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Siswa</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($baris as $id => $siswa)
                        <tr wire:key="baris-{{ $id }}">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-zinc-900">{{ $siswa['nama'] }}</p>
                                <p class="text-xs text-zinc-500">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <select wire:model.live="status.{{ $id }}"
                                        class="rounded-lg border-zinc-300 text-sm text-zinc-900">
                                    @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                        <option value="{{ $opsi->value }}">{{ ucfirst($opsi->value) }}</option>
                                    @endforeach
                                </select>
                                @error("status.{{ $id }}")
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="px-4 py-3">
                                @if ($this->alasanTerlihat($id))
                                    <input type="text" wire:model="note.{{ $id }}"
                                           placeholder="Contoh: Demam, acara keluarga, dll"
                                           class="w-full rounded-lg border-zinc-300 text-sm" />
                                    @error("note.{{ $id }}")
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                @else
                                    <span class="text-xs text-zinc-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
```

`wire:model.live` pada `status` memicu `updatedStatus` setiap perubahan, jadi alasan hilang seketika di layar — tanpa tombol simpan. Alasan sendiri **tetap opsional** ([D4]); kolomnya hanya visibil, tidak memaksa isi.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AttendanceEntryFormTest
```

Expected: 5 tests pass (5 test baru).

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: tandai massal dan kolom alasan kondisional di form absensi"
```

---

## Task 5: Menyimpan lewat `AttendanceRecorder`

**Files:**
- Modify: `app/Livewire/AttendanceEntry.php`
- Modify: `resources/views/livewire/attendance-entry.blade.php`
- Test: `tests/Feature/Livewire/AttendanceEntrySaveTest.php`

**Consumes:** `AttendanceRecorder::record(Schedule, Carbon, Collection, User): int` dari Plan 1 Task 6
**Produces:** form yang benar-benar menyimpan, tanpa component yang menyentuh database secara langsung

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AttendanceEntrySaveTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntrySaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function kelasDenganSiswa(int $jumlahSiswa = 3): array
    {
        $kelas = Schedule::factory()->create()->schoolClass;

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $kelas->id,
                'name' => 'Siswa ' . $urutan,
            ]);
        }

        return [$kelas, Student::query()->where('class_id', $kelas->id)->pluck('id')];
    }

    public function test_simpan_menulis_semua_baris_absensi(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.' . $ids->last(), 'sakit')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('sukses', true)
            ->assertSee('Simpan Perubahan');

        $this->assertSame(3, Attendance::count());

        foreach ($ids as $id) {
            $baris = Attendance::where('student_id', $id)->firstOrFail();

            $this->assertSame($guru->id, $baris->recorded_by);
            $this->assertNull($baris->updated_by);
            $this->assertSame(today()->toDateString(), $baris->date->toDateString());
        }

        $this->assertSame(
            AttendanceStatus::Sakit,
            Attendance::where('student_id', $ids->last())->firstOrFail()->status,
        );
    }

    public function test_submit_dua_kali_tidak_menggandakan_baris(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->actingAs($admin);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.' . $ids->first(), 'alpa')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(3, Attendance::count());

        $baris = Attendance::where('student_id', $ids->first())->firstOrFail();

        $this->assertSame(AttendanceStatus::Alpa, $baris->status);
        $this->assertSame($guru->id, $baris->recorded_by);
        $this->assertSame($admin->id, $baris->updated_by);
    }

    public function test_guru_ditolak_untuk_tanggal_di_luar_jendela(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $this->kelasDenganSiswa(2);
        $this->actingAs($guru);

        $this->get(route('absensi.show', [$jadwal, today()->subDays(5)->toDateString()]))
            ->assertForbidden();

        $this->assertSame(0, Attendance::count());
    }

    public function test_admin_boleh_menyimpan_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => User::factory()->guru()->create()->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($admin);
        $lampu = today()->subDays(5)->toDateString();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal, 'tanggal' => $lampu])
            ->assertOk()
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(2, Attendance::whereDate('date', $lampu)->count());
        $this->assertSame(2, $ids->count());
    }

    public function test_siswa_dari_kelas_lain_ditolak(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $asing = Student::factory()->create();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status', [$asing->id => 'hadir'])
            ->call('simpan')
            ->assertHasErrors('form');

        $this->assertSame(0, Attendance::count());
        $this->assertSame(2, $ids->count());
    }

    public function test_satu_baris_gagal_membatalkan_seluruh_batch(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $asing = Student::factory()->create();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status', [
                $ids->first() => 'hadir',
                $asing->id => 'hadir',
                $ids->last() => 'hadir',
            ])
            ->call('simpan')
            ->assertHasErrors('form');

        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->first()]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->last()]);
    }

    public function test_alasan_dibersihkan_saat_status_kembali_hadir(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(1);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $id = $ids->first();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.' . $id, 'izin')
            ->set('note.' . $id, 'Acara keluarga')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Acara keluarga', Attendance::where('student_id', $id)->firstOrFail()->note);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.' . $id, 'izin')
            ->set('note.' . $id, 'Acara keluarga')
            ->set('status.' . $id, 'hadir')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertNull(Attendance::where('student_id', $id)->firstOrFail()->note);
    }
}
```

`test_satu_baris_gagal_membatalkan_seluruh_batch` adalah bukti rollback-nya. Baris `$ids->first()` dikirim **lebih dulu**, jadi service benar-benar sudah menulis baris itu sebelum kena penolakan di baris kedua. Kalau `DB::transaction` di Plan 1 Task 6 tidak membungkus seluruh loop, `assertDatabaseMissing` untuk `$ids->first()` akan gagal — bukan hanya hitungan total.

- [ ] **Step 2: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter=AttendanceEntrySaveTest
```

Expected: FAIL — `Method simpan does not exist`.

- [ ] **Step 3: Tulis implementasi**

`app/Livewire/AttendanceEntry.php` — tambahkan properti ini berdampingan dengan `$sudahDisimpan`:

```php
    public bool $sukses = false;
```

Lalu tambahkan method berikut. Tambahkan juga empat import ini di bagian atas file:

```php
use App\Services\AttendanceRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
```

```php
    public function rules(): array
    {
        return [
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status kehadiran wajib diisi.',
            'status.array' => 'Status kehadiran wajib diisi.',
            'status.*.required' => 'Status kehadiran siswa ini wajib dipilih.',
            'status.*.enum' => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, alpa.',
            'note.array' => 'Data alasan tidak valid.',
            'note.*.string' => 'Alasan harus berupa teks.',
            'note.*.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function simpan(): void
    {
        $this->validate();

        $rows = new Collection(collect(array_keys($this->status))->map(fn (string $id) => [
            'student_id' => $id,
            'status' => $this->status[$id],
            'note' => $this->noteUntuk($id),
        ])->all());

        try {
            app(AttendanceRecorder::class)->record(
                $this->schedule,
                $this->tanggal,
                $rows,
                auth()->user(),
            );
        } catch (AuthorizationException) {
            throw ValidationException::withMessages([
                'form' => 'Kamu tidak berwenang mengisi absensi jadwal ini. Hubungi admin.',
            ]);
        } catch (ValidationException $penolakan) {
            throw ValidationException::withMessages($penolakan->errors() + [
                'form' => 'Absensi gagal disimpan. Tidak ada satu pun data yang tersimpan.',
            ]);
        }

        $this->sudahDisimpan = true;
        $this->sukses = true;
    }

    /**
     * Alasan hanya disimpan bersama status izin, sakit, atau alpa.
     * Status hadir selalu ditulis dengan alasan kosong, walaupun
     * masih ada sisa teks di field.
     */
    private function noteUntuk(string $studentId): ?string
    {
        if (($this->status[$studentId] ?? '') === AttendanceStatus::Hadir->value) {
            return null;
        }

        $catatan = trim((string) ($this->note[$studentId] ?? ''));

        return $catatan === '' ? null : $catatan;
    }
```

`$rows` dibangun dari `array_keys($this->status)` — persis apa yang dikirim, tanpa penyaringan. Keanggotaan kelas bukan urusan form; satu-satunya penjaga adalah `AttendanceRecorder`, dan test memperlihukannya secara langsung. Baris yang bukan anggota kelas ditolak service dan seluruh batch rollback.

`resources/views/livewire/attendance-entry.blade.php` — tambahkan blok umpan balik dan tombol simpan. Ganti seluruh isi:

```blade
<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-medium text-zinc-500">
                {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}
            </p>
            <p class="mt-1 text-4xl font-bold tracking-tight text-zinc-900">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if ($sukses)
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                Absensi tersimpan. Status siswa bisa diubah lagi selama jadwal hari ini atau kemarin masih dibuka.
            </div>
        @endif

        @if ($errors->has('form'))
            <div class="rounded-xl border border-red-300 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-800">{{ $errors->first('form') }}</p>
                @foreach ($errors->except('form') as $pesan)
                    <p class="mt-1 text-sm text-red-700">{{ $pesan }}</p>
                @endforeach
            </div>
        @endif

        <form wire:submit="simpan" class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="tandaiSemua('hadir')"
                        class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                    Tandai Semua Hadir
                </button>
                <button type="button" wire:click="tandaiSemua('alpa')"
                        class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                    Tandai Semua Alpa
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Siswa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($baris as $id => $siswa)
                            <tr wire:key="baris-{{ $id }}">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-zinc-900">{{ $siswa['nama'] }}</p>
                                    <p class="text-xs text-zinc-500">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <select wire:model.live="status.{{ $id }}"
                                            class="rounded-lg border-zinc-300 text-sm text-zinc-900">
                                        @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                            <option value="{{ $opsi->value }}">{{ ucfirst($opsi->value) }}</option>
                                        @endforeach
                                    </select>
                                    @error("status.{{ $id }}")
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="px-4 py-3">
                                    @if ($this->alasanTerlihat($id))
                                        <input type="text" wire:model="note.{{ $id }}"
                                               placeholder="Contoh: Demam, acara keluarga, dll"
                                               class="w-full rounded-lg border-zinc-300 text-sm" />
                                        @error("note.{{ $id }}")
                                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                        @enderror
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="rounded-lg bg-zinc-800 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-700">
                    {{ $sudahDisimpan ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                </button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AttendanceEntrySaveTest
```

Expected: 7 tests pass (7 test baru).

- [ ] **Step 5: Commit**

```powershell
git add -A
git commit -m "feat: simpan absensi lewat AttendanceRecorder tanpa menulis dari component"
```

---

## Task 6: Batas edit 1 hari ke belakang di layar

**Files:**
- Modify: `app/Livewire/AttendanceEntry.php`
- Modify: `resources/views/livewire/attendance-entry.blade.php`
- Modify: `tests/Feature/Livewire/AttendanceEntryAccessTest.php`
- Test: `tests/Feature/Livewire/AttendanceEntryWindowTest.php`

**Consumes:** `AdminContactsQuery` dari Task 1, `SchedulePolicy::recordAttendance` Plan 1 Task 4
**Produces:** guru yang membuka tanggal terlalu lama atau terlalu awal melihat pesan berbahasa manusia plus kontak admin, bukan form kosong tanpa penjelasan

Task 2 sudah menolak 403. Aturan bisnisnya tidak berubah — yang berubah adalah cara aturan itu disampaikan ke orang yang salah. Tidak ada role yang dipakai untuk memilih pesannya: kalimatnya diturunkan dari perbandingan tanggal saja, jadi admin pun mendapat kalimat yang sama persis bila suatu saat aturan jendela diperketat.

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AttendanceEntryWindowTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function jadwalMilik(User $guru): Schedule
    {
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        Student::factory()->count(2)->create(['class_id' => $jadwal->class_id]);

        return $jadwal;
    }

    public function test_guru_melihat_pesan_batas_edit_untuk_tanggal_lampau(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', false)
            ->assertSee('Absensi ini sudah lewat 1 hari. Hubungi admin.');
    }

    public function test_guru_melihat_pesan_batas_edit_untuk_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal' => today()->addDay()->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', false)
            ->assertSee('Absensi untuk tanggal ini belum bisa diisi.');
    }

    public function test_form_tidak_dirender_saat_jendela_sudah_tertutup(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertDontSee('Simpan Absensi')
            ->assertDontSee('Tandai Semua Hadir')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');
    }

    public function test_kontak_admin_tampil_saat_absensi_terkunci(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        User::factory()->admin()->create([
            'name' => 'Operator Sekolah',
            'email' => 'operator@sekolah.sch.id',
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertSee('Operator Sekolah')
            ->assertSee('operator@sekolah.sch.id');
    }

    public function test_admin_tetap_melihat_form_untuk_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwalMilik($admin);
        $this->actingAs($admin);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal' => today()->subDays(30)->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', true)
            ->assertSee('Simpan Absensi')
            ->assertDontSee('Absensi ini sudah lewat 1 hari. Hubungi admin.');
    }
}
```

- [ ] **Step 2: Ubah test Task 2 agar sesuai perilaku baru**

Satu assertion di `tests/Feature/Livewire/AttendanceEntryAccessTest.php` berubah karena 403 digantikan pesan berbahasa manusia. Ganti **hanya method ini**:

```php
    public function test_guru_tidak_bisa_membuka_tanggal_di_luar_jendela(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->actingAs($guru)
            ->get(route('absensi.show', [$jadwal, today()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertSee('Absensi ini sudah lewat 1 hari. Hubungi admin.')
            ->assertDontSee('Simpan Absensi');
    }
```

Empat test lain di file itu tidak berubah. Jumlah test di file ini tetap 5.

- [ ] **Step 3: Jalankan test, pastikan gagal**

```powershell
php artisan test --filter="AttendanceEntryWindowTest|AttendanceEntryAccessTest"
```

Expected: FAIL — `assertSee('Absensi ini sudah lewat 1 hari. Hubungi admin.')` gagal karena `mount()` masih melempar 403.

- [ ] **Step 4: Tulis implementasi**

`app/Livewire/AttendanceEntry.php` — ganti seluruh isi:

```php
<?php

namespace App\Livewire;

use App\Attendance\Queries\AdminContactsQuery;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Services\AttendanceRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    public Carbon $tanggal;

    public bool $bolehUbah = false;

    public bool $sudahDisimpan = false;

    public bool $sukses = false;

    /**
     * @var array<string,array{nama:string,nisn:string,jk:string}>
     */
    public array $baris = [];

    /**
     * @var array<string,string>
     */
    public array $status = [];

    /**
     * @var array<string,string|null>
     */
    public array $note = [];

    public function mount(Schedule $schedule, ?string $tanggal = null): void
    {
        $this->schedule = $schedule;
        $this->tanggal = $this->tanggalDari($tanggal);

        Gate::authorize('view', $this->schedule);

        $this->bolehUbah = Gate::allows('recordAttendance', [$this->schedule, $this->tanggal]);

        $tersimpan = Attendance::query()
            ->where('schedule_id', $this->schedule->id)
            ->whereDate('date', $this->tanggal->toDateString())
            ->get(['student_id', 'status', 'note'])
            ->keyBy('student_id');

        $this->sudahDisimpan = $tersimpan->isNotEmpty();

        $siswa = $this->schedule->schoolClass->students()->orderBy('name')->get();

        foreach ($siswa as $orang) {
            $baris = $tersimpan->get($orang->id);

            $this->baris[$orang->id] = [
                'nama' => $orang->name,
                'nisn' => $orang->nisn,
                'jk' => $orang->gender->value,
            ];

            $this->status[$orang->id] = $baris?->status?->value ?? AttendanceStatus::Hadir->value;
            $this->note[$orang->id] = $baris?->note;
        }
    }

    public function tandaiSemua(string $status): void
    {
        $pilihan = AttendanceStatus::from($status);

        foreach (array_keys($this->baris) as $id) {
            $this->status[$id] = $pilihan->value;
        }

        $this->note = [];
    }

    /**
     * Alasan hanya relevan untuk izin, sakit, dan alpa. Saat guru
     * mengembalikan status ke hadir, alasan lama dibuang supaya tidak
     * ikut tersimpan sebagai sisa.
     */
    public function updatedStatus(mixed $value, string $key): void
    {
        if ($value !== AttendanceStatus::Hadir->value) {
            return;
        }

        unset($this->note[$key]);
    }

    public function alasanTerlihat(string $studentId): bool
    {
        return ($this->status[$studentId] ?? AttendanceStatus::Hadir->value)
            !== AttendanceStatus::Hadir->value;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status kehadiran wajib diisi.',
            'status.array' => 'Status kehadiran wajib diisi.',
            'status.*.required' => 'Status kehadiran siswa ini wajib dipilih.',
            'status.*.enum' => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, alpa.',
            'note.array' => 'Data alasan tidak valid.',
            'note.*.string' => 'Alasan harus berupa teks.',
            'note.*.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function simpan(): void
    {
        if (! $this->bolehUbah) {
            throw ValidationException::withMessages([
                'form' => $this->pesanTerkunci(),
            ]);
        }

        $this->validate();

        $rows = new Collection(collect(array_keys($this->status))->map(fn (string $id) => [
            'student_id' => $id,
            'status' => $this->status[$id],
            'note' => $this->noteUntuk($id),
        ])->all());

        try {
            app(AttendanceRecorder::class)->record(
                $this->schedule,
                $this->tanggal,
                $rows,
                auth()->user(),
            );
        } catch (AuthorizationException) {
            throw ValidationException::withMessages([
                'form' => 'Kamu tidak berwenang mengisi absensi jadwal ini. Hubungi admin.',
            ]);
        } catch (ValidationException $penolakan) {
            throw ValidationException::withMessages($penolakan->errors() + [
                'form' => 'Absensi gagal disimpan. Tidak ada satu pun data yang tersimpan.',
            ]);
        }

        $this->sudahDisimpan = true;
        $this->sukses = true;
    }

    public function pesanTerkunci(): string
    {
        if ($this->tanggal->isBefore(today()->startOfDay()->subDay())) {
            return 'Absensi ini sudah lewat 1 hari. Hubungi admin.';
        }

        return 'Absensi untuk tanggal ini belum bisa diisi.';
    }

    /**
     * Tanggal terkunci. Nilai dari rute adalah satu-satunya sumber selain
     * hari ini, dan bentuknya harus persis Y-m-d supaya tidak ada tanggal
     * liar yang lolos ke query.
     */
    private function tanggalDari(?string $tanggal): Carbon
    {
        if ($tanggal === null) {
            return now()->startOfDay();
        }

        if (! Carbon::hasFormat($tanggal, 'Y-m-d')) {
            abort(404);
        }

        return Carbon::parse($tanggal, 'Asia/Jakarta')->startOfDay();
    }

    /**
     * Alasan hanya disimpan bersama status izin, sakit, atau alpa.
     * Status hadir selalu ditulis dengan alasan kosong, walaupun
     * masih ada sisa teks di field.
     */
    private function noteUntuk(string $studentId): ?string
    {
        if (($this->status[$studentId] ?? '') === AttendanceStatus::Hadir->value) {
            return null;
        }

        $catatan = trim((string) ($this->note[$studentId] ?? ''));

        return $catatan === '' ? null : $catatan;
    }

    public function render()
    {
        return view('livewire.attendance-entry', [
            'tanggal' => $this->tanggal,
            'kontakAdmin' => $this->bolehUbah
                ? new Collection
                : app(AdminContactsQuery::class)->active(),
        ]);
    }
}
```

`pesanTerkunci()` hanya membandingkan tanggal dengan `today()`. Tidak ada `isAdmin()`, tidak ada pembacaan role. Kalau suatu saat jendela edit diperketat, hanya `SchedulePolicy::recordAttendance` yang berubah, dan pesannya tetap benar karena diturunkan dari tanggal yang sama.

`resources/views/livewire/attendance-entry.blade.php` — ganti seluruh isi:

```blade
<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-sm font-medium text-zinc-500">
                {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}
            </p>
            <p class="mt-1 text-4xl font-bold tracking-tight text-zinc-900">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if (! $bolehUbah)
            <div class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-6">
                <p class="text-base font-semibold text-amber-900">{{ $this->pesanTerkunci() }}</p>
                @if ($kontakAdmin->isNotEmpty())
                    <p class="text-sm text-amber-900">Hubungi salah satu admin sekolah:</p>
                    <ul class="space-y-1 text-sm text-amber-900">
                        @foreach ($kontakAdmin as $admin)
                            <li>
                                <span class="font-medium">{{ $admin['nama'] }}</span>
                                @if ($admin['email'])
                                    - <a class="underline" href="mailto:{{ $admin['email'] }}">{{ $admin['email'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-amber-900">Belum ada akun admin yang aktif. Hubungi operator sekolah.</p>
                @endif
            </div>
        @else
            @if ($sukses)
                <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                    Absensi tersimpan. Status siswa bisa diubah lagi selama jadwal hari ini atau kemarin masih dibuka.
                </div>
            @endif

            @if ($errors->has('form'))
                <div class="rounded-xl border border-red-300 bg-red-50 p-4">
                    <p class="text-sm font-semibold text-red-800">{{ $errors->first('form') }}</p>
                    @foreach ($errors->except('form') as $pesan)
                        <p class="mt-1 text-sm text-red-700">{{ $pesan }}</p>
                    @endforeach
                </div>
            @endif

            <form wire:submit="simpan" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="tandaiSemua('hadir')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                        Tandai Semua Hadir
                    </button>
                    <button type="button" wire:click="tandaiSemua('alpa')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800">
                        Tandai Semua Alpa
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Siswa</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($baris as $id => $siswa)
                                <tr wire:key="baris-{{ $id }}">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-zinc-900">{{ $siswa['nama'] }}</p>
                                        <p class="text-xs text-zinc-500">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select wire:model.live="status.{{ $id }}"
                                                class="rounded-lg border-zinc-300 text-sm text-zinc-900">
                                            @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                                <option value="{{ $opsi->value }}">{{ ucfirst($opsi->value) }}</option>
                                            @endforeach
                                        </select>
                                        @error("status.{{ $id }}")
                                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($this->alasanTerlihat($id))
                                            <input type="text" wire:model="note.{{ $id }}"
                                                   placeholder="Contoh: Demam, acara keluarga, dll"
                                                   class="w-full rounded-lg border-zinc-300 text-sm" />
                                            @error("note.{{ $id }}")
                                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <span class="text-xs text-zinc-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-zinc-800 px-6 py-3 text-sm font-semibold text-white hover:bg-zinc-700">
                        {{ $sudahDisimpan ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter="AttendanceEntryWindowTest|AttendanceEntryAccessTest"
```

Expected: 10 tests pass - 5 test `AttendanceEntryWindowTest` yang baru, ditambah 5 test `AttendanceEntryAccessTest` yang sudah ada dan hanya satu method-nya yang diganti.

- [ ] **Step 6: Commit**

```powershell
git add -A
git commit -m "feat: batas edit 1 hari ditampilkan sebagai pesan dan kontak admin"
```

---

## Task 7: Ringkasan test dan verifikasi akhir

**Files:**
- Test: `tests/Feature/Livewire/AbsensiRoutesTest.php`

**Consumes:** semua task sebelumnya
**Produces:** penjaga tabel rute supaya plan ini tidak bisa disalahpahami

- [ ] **Step 1: Tulis test gagal**

`tests/Feature/Livewire/AbsensiRoutesTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AbsensiRoutesTest extends TestCase
{
    public function test_tiga_rute_absensi_terdaftar(): void
    {
        $this->assertTrue(Route::has('jadwal.index'));
        $this->assertTrue(Route::has('absensi.create'));
        $this->assertTrue(Route::has('absensi.show'));
    }

    public function test_rute_absensi_wajib_login(): void
    {
        $middleware = Route::getRoutes()->getByName('absensi.create')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
    }

    public function test_rute_absensi_menolak_akun_tidak_aktif(): void
    {
        $middleware = Route::getRoutes()->getByName('absensi.show')->gatherMiddleware();

        $this->assertContains(EnsureUserIsActive::class, $middleware);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan lulus**

```powershell
php artisan test --filter=AbsensiRoutesTest
```

Expected: 3 tests pass (3 test baru). Kalau gagal, blok rute di `routes/web.php` belum terpasang di dalam grup `auth`.

- [ ] **Step 3: Jalankan seluruh suite**

```powershell
php artisan test
```

Expected: seluruh test Plan 1-4 lulus, 0 gagal, 0 skipped.

- [ ] **Step 4: Verifikasi tabel rute**

```powershell
php artisan route:list --path=absensi
```

Expected: dua baris `/absensi/{schedule}`, satu baris `/absensi/{schedule}/{tanggal}`, plus `/jadwal`. Kalau tidak muncul, route masih di luar grup `auth` dan middleware akun aktif tidak terpasang.

- [ ] **Step 5: Cek manual di browser**

1. `php artisan migrate --seed`, lalu `php artisan serve`.
2. Masuk sebagai guru hasil seeder demo lewat NUPTK.
3. Buka `/jadwal`. Jadwal hari ini tampil urut jam, badge "Belum absen" selama belum ada baris absensi.
4. Ubah `day_of_week` satu jadwal di `tinker` ke hari lain, refresh — jadwal itu hilang dari daftar.
5. Klik "Isi Absensi". Jam tampil besar, tanggal terkunci dan tidak bisa diklik.
6. Klik "Tandai Semua Alpa", lalu ubah satu baris jadi "Sakit" — kolom alasan muncul hanya di baris itu, dan masih boleh kosong.
7. Isi alasan di satu baris izin, ubah statusnya jadi hadir — kolomnya langsung hilang.
8. Simpan. Badge di `/jadwal` berubah jadi "Sudah absen", tombol jadi "Simpan Perubahan".
9. Buka lagi halaman itu. Data lama tampil, bukan form kosong.
10. Ubah satu status, simpan lagi. Baris di database tetap satu, `updated_by` terisi.
11. Buka `/absensi/{id}/{tanggal 5 hari lalu}` sebagai guru — muncul "Absensi ini sudah lewat 1 hari. Hubungi admin." tanpa tombol simpan.
12. Ulangi langkah 11 sebagai admin — form penuh tampil dan bisa disimpan.
13. Nonaktifkan akun guru dari menu user, lalu pakai session yang masih hidup — halaman absensi menolak dengan 403.

- [ ] **Step 6: Commit**

```powershell
git add -A
git commit -m "test: penjaga rute absensi dan verifikasi akhir plan 4"
```

---

## Verifikasi akhir

```powershell
php artisan test
php artisan route:list --path=absensi
```

Target: **semua lulus, 0 gagal, 0 skipped.**

Jumlah test baru plan ini:

| Task | File | Test baru |
|---|---|---|
| 1 | `TodayScheduleTest` | 6 |
| 2 | `AttendanceEntryAccessTest` | 5 |
| 3 | `AttendanceEntryLoadTest` | 6 |
| 4 | `AttendanceEntryFormTest` | 5 |
| 5 | `AttendanceEntrySaveTest` | 7 |
| 6 | `AttendanceEntryWindowTest` | 5 |
| 7 | `AbsensiRoutesTest` | 3 |
| | **Total test baru** | **37** |

Setiap method test di dokumen ini dicetak penuh tepat satu kali, kecuali satu. Task 6 Step 2 mencetak ulang `test_guru_tidak_bisa_membuka_tanggal_di_luar_jendela` yang sudah ditulis di Task 2, supaya perubahannya kelihatan tanpa perlu membuka file lama. Karena itu menghitung baris deklarasi method di dalam blok test plan ini menghasilkan 38 baris untuk 37 test yang berbeda.

Cakupan spec §5.1-§5.4 dan Tier 1, 2, 4 §8:

| Persyaratan spec | Covered di |
|---|---|
| `TodaySchedule` menampilkan jadwal hari ini saja | Task 1, `TodayScheduleTest` |
| Hari dari Carbon + timezone, bukan browser | Task 1, `test_mengurutkan_jadwal_menurut_jam_mulai` dan `test_hari_sabtu_menampilkan_empty_state` |
| Penanda "Sudah absen" tanpa N+1 | Task 1, `test_status_sudah_absen_muncul_setelah_baris_terisi` |
| Scoping lewat Policy, bukan role di view | Task 1, `TodaySchedulesQuery` + `test_jadwal_guru_lain_tidak_muncul` |
| Guru tidak bisa buka absensi jadwal orang lain | Task 2, `test_guru_mengajar_jadwal_lain_mendapat_403` |
| `is_active = false` tidak bisa masuk | Task 2, `test_akun_tidak_aktif_mendapat_403` |
| Tanggal terkunci, tidak bisa diubah guru | Task 3, `test_tanggal_terkunci_ke_hari_ini` dan `test_tidak_ada_input_tanggal_yang_bisa_diubah` |
| Jam jadwal tampil besar di atas form | Task 3, `test_menampilkan_jam_jadwal_besar_di_atas_form` |
| Default semua "hadir" | Task 3, `test_semua_siswa_default_hadir_dan_terurut_nama` |
| Absensi lama tampil + tombol "Simpan Perubahan" | Task 3, `test_absensi_lama_dimuat_bukan_form_kosong` |
| "Tandai Semua Hadir" dan "Tandai Semua Alpa" | Task 4, `AttendanceEntryFormTest` |
| Alasan hanya saat izin/sakit/alpa, opsional | Task 4, `test_kolom_alasan_muncul_untuk_izin_sakit_dan_alpa` |
| Alasan dibuang saat kembali ke hadir | Task 4 + Task 5, `test_alasan_dibersihkan_saat_status_kembali_hadir` |
| Tidak ada auto-save | Task 4, `test_tidak_ada_autosave_saat_status_diubah` |
| Component tidak menulis database langsung | Task 5, seluruh `AttendanceEntrySaveTest` |
| Submit dua kali tetap satu baris | Task 5, `test_submit_dua_kali_tidak_menggandakan_baris` |
| Siswa dari kelas lain ditolak | Task 5, `test_siswa_dari_kelas_lain_ditolak` |
| Satu baris gagal membatalkan seluruh batch | Task 5, `test_satu_baris_gagal_membatalkan_seluruh_batch` |
| Guru maks 1 hari ke belakang | Task 2 + Task 6, `test_guru_ditolak_untuk_tanggal_di_luar_jendela` dan `AttendanceEntryWindowTest` |
| Admin bebas kapan saja | Task 5 + Task 6, `test_admin_boleh_menyimpan_tanggal_lampau` dan `test_admin_tetap_melihat_form_untuk_tanggal_lampau` |
| Batas edit dijelaskan ke pengguna | Task 6, `test_guru_melihat_pesan_batas_edit_untuk_tanggal_lampau` |
| Kontak admin sebagai jalan keluar | Task 6, `test_kontak_admin_tampil_saat_absensi_terkunci` |
| Rute terlindungi di balik login | Task 7, `AbsensiRoutesTest` |

Tidak ada aturan spec §5 yang belum punya task dan test.

---

## Di luar lingkup plan ini

Sengaja ditunda ke plan berikutnya, bukan karena tidak penting:

| Item | Masuk |
|---|---|
| `HomeroomClassRecap`, `TeacherSubjectRecap`, drill-down siswa per rapor | Plan 5 |
| `AdminAttendanceReport` dan export Excel/PDF | Plan 5 |
| Tab "Kelas saya" untuk wali kelas | Plan 5 |
| Riwayat absensi per siswa (menu student detail) | Plan 5 |
| Notifikasi otomatis ke orang tua saat siswa alpa | Tidak v1 (spec §10) |
| QR code dan face recognition untuk absensi | Tidak v1 (spec §10) |
| Rekap harian per jadwal sebagai halaman tersendiri | Tidak ada di spec §5.1; rekap selalu lewat Query Object Plan 5 |



