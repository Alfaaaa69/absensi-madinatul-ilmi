# Plan 5 - Rekap, Laporan & Deploy

> **Untuk agent yang mengerjakan:** WAJIB memakai skill `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan plan ini task per task. Format `- [ ]` dipakai untuk melacak progres.

**Goal:** Mengubah tiga Query Object rekap yang sudah jadi (Plan 1) menjadi tiga halaman rekap yang dipakai guru, wali kelas, dan admin — ditambah drill-down siswa, export Excel/PDF, dan deploy ke shared hosting cPanel.

**Architecture:** Setiap halaman adalah satu Livewire full-page component yang tidak pernah menulis SQL. Halaman mengambil `RecapPeriod` dari `App\Recap\RecapPeriod`, meneruskannya ke Query Object, lalu **hanya menampilkan** `RecapResult` yang dikembalikan. Action export memanggil `RecapExporter` dengan `RecapResult` yang sama. Validasi periode lewat `RecapPeriod::assertWithin()` yang melempar `RecapPeriodOutOfRange`, ditangkap component dan ditampilkan sebagai pesan berbahasa manusia.

**Spec:** `docs/specs/2026-09-26-sistem-absensi-sekolah-design.md`

---

## Global Constraints

Nilai-nilai ini berlaku untuk **setiap task**. Jangan menyimpang tanpa keputusan manusia.

- **Gate versi sudah diputuskan di Plan 1 Task 1: PHP 8.3, Laravel `^13.0`.** Tidak ada fallback ke PHP 8.2 / Laravel 12 (D16). Baca, jangan putuskan ulang. **`APP_TIMEZONE=Asia/Jakarta` wajib di `.env`** karena default UTC membuat `date('Y-m-d')` bergeser sehari saat absensi jam 7 pagi.
- **`QUEUE_CONNECTION=sync`** karena shared hosting tidak menjalankan `queue:work`, dan **primary key UUID** berlaku di semua tabel — id yang dikirim browser divalidasi lewat `rules()`.
- **Wali kelas bukan role.** `role` hanya berisi `admin` atau `guru`; status wali kelas ditentukan relasi `User::homeroomClasses()`.
- **Scoping hanya di Policy dan Query Object.** Menulis kondisi role atau `WHERE` di dalam component/view adalah bug, bukan fitur. Component yang butuh daftar kelas milik wali memanggil `HomeroomClassRecapQuery::ownedClasses()`.
- **`attendances` tidak memakai soft delete** dan unique constraint `(student_id, schedule_id, date)` tidak boleh di-bypass. Plan ini hanya membaca tabel itu.
- **Test memakai SQLite in-memory** lewat `phpunit.xml` Plan 1 Task 1. `.env` lokal tetap MySQL dan tidak pernah disentuh test. Semua pesan, label, dan exception dalam bahasa Indonesia.
- **Satu sumber angka: layar dan file export wajib memakai `RecapResult` yang sama.** Action export tidak boleh membangun ulang matrix-nya sendiri.
- **Tidak ada kode yang ditulis-ngawur lalu disalin ulang.** Setiap snippet adalah kode final yang langsung ditulis ke disk. Kalau test gagal, benahi penyebabnya.

---

## File Structure

| Path | Dibuat atau diubah di |
|---|---|
| `app/Livewire/Concerns/InteractsWithRecapPeriod.php` | Task 1, dipakai ulang Task 3 dan 5 |
| `app/Livewire/HomeroomClassRecap.php` | Task 1, ditambah Task 2 |
| `app/Livewire/TeacherSubjectRecap.php` | Task 3 |
| `app/Livewire/AdminAttendanceReport.php` | Task 5, ditambah Task 6 |
| `app/Recap/RecapExporter.php` | Task 6 dan 8 (sudah ada dari Plan 1) |
| `app/Recap/RecapQueries/HomeroomClassRecapQuery.php` | Task 1 dan 2 (lima query lain sudah ada dari Plan 1) |
| `resources/views/recap/partials/matrix.blade.php` | Task 1 |
| `resources/views/recap/partials/filter-periode.blade.php` | Task 1 |
| `resources/views/recap/partials/periode-error.blade.php` | Task 4 |
| `resources/views/recap/pdf.blade.php` | Task 6 |
| `resources/views/livewire/*.blade.php` | Task 1, 3, 5 |
| `config/rekap.php` | Task 8 |
| `tests/Support/RecapScenario.php` | Task 1, dipakai semua task |

## Task 1: `HomeroomClassRecap` - tab "Kelas Saya"

- Create: `tests/Support/RecapScenario.php` (helper skenario bersama)
- Create: `app/Livewire/Concerns/InteractsWithRecapPeriod.php`
- Create: `app/Livewire/HomeroomClassRecap.php`
- Create: `resources/views/recap/partials/matrix.blade.php`
- Create: `resources/views/recap/partials/filter-periode.blade.php`
- Create: `resources/views/livewire/homeroom-class-recap.blade.php`
- Create: `resources/views/recap/index.blade.php`
- Modify: `app/Recap/RecapQueries/HomeroomClassRecapQuery.php` (tambah `forClass()` dan `ownedClasses()`)
- Modify: `app/Providers/AppServiceProvider.php` (view composer untuk tab rekap)
- Modify: `routes/web.php` (tambah `/rekap`)
- Test: `tests/Feature/Livewire/HomeroomClassRecapTest.php`


Kelas dibuat ulang tiap tahun (D2), jadi seorang wali bisa memiliki dua baris `classes` pada tahun berbeda. Halaman ini karena itu tidak pernah mengasumsikan "pasti satu kelas": kalau lebih dari satu, muncul pemilih kelas.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Support/RecapScenario.php` — helper tetap yang dipakai semua task, supaya tiap test cuma satu baris setup:
```php
<?php
namespace Tests\Support;
use App\Enums\AttendanceStatus;
use App\Models\{AcademicYear, Attendance, Schedule, SchoolClass, Student, Subject, User};
use Illuminate\Support\Carbon;
/** Tahun ajaran aktif, wali kelas yang juga mengajar, satu kelas, satu siswa, satu jadwal Matematika, dua absensi September. */
final class RecapScenario
{
    public static function waliKelas(): object
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));
        $tahun = AcademicYear::factory()->create(['name' => '2025/2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_active' => true]);
        $wali = User::factory()->guru()->create(['name' => 'Budi Santoso']);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'homeroom_teacher_id' => $wali->id, 'name' => 'VII-A']);
        $siswa = self::siswaDi($kelas, 'Ahmad Fauzi');
        $jadwal = Schedule::factory()->create(['class_id' => $kelas->id, 'subject_id' => Subject::factory()->create(['name' => 'Matematika'])->id, 'teacher_id' => $wali->id]);
        self::absen($siswa, $jadwal, '2025-09-01', AttendanceStatus::Hadir);
        self::absen($siswa, $jadwal, '2025-09-02', AttendanceStatus::Izin, 'Surat dokter');
        return (object) compact('tahun', 'wali', 'kelas', 'siswa', 'jadwal');
    }
    public static function kelasLain(object $skenario, string $nama, User $wali): SchoolClass
    {
        return SchoolClass::factory()->create(['academic_year_id' => $skenario->tahun->id, 'homeroom_teacher_id' => $wali->id, 'name' => $nama]);
    }
    public static function siswaDi(SchoolClass $kelas, string $nama): Student
    {
        return Student::factory()->create(['class_id' => $kelas->id, 'name' => $nama]);
    }
    public static function mapelLain(object $skenario, string $nama, User $guru): Schedule
    {
        return Schedule::factory()->create(['class_id' => $skenario->kelas->id, 'subject_id' => Subject::factory()->create(['name' => $nama])->id, 'teacher_id' => $guru->id]);
    }
    public static function absen(Student $siswa, Schedule $jadwal, string $tanggal, AttendanceStatus $status, ?string $catatan = null, ?User $oleh = null): Attendance
    {
        return Attendance::create(['student_id' => $siswa->id, 'schedule_id' => $jadwal->id, 'date' => $tanggal, 'status' => $status, 'note' => $catatan, 'recorded_by' => ($oleh ?? $jadwal->teacher_id)]);
    }
}
```
`Carbon::setTestNow()` mengikat tanggal hari ini supaya rekap bulanan default selalu September 2025, bukan tanggal mesin.

`tests/Feature/Livewire/HomeroomClassRecapTest.php`:
```php
<?php
namespace Tests\Feature\Livewire;
use App\Livewire\HomeroomClassRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class HomeroomClassRecapTest extends TestCase
{
    use RefreshDatabase;
    public function test_menampilkan_rekap_bulanan_kelas_wali(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSet('periodType', 'monthly')
            ->assertSee('VII-A')->assertSee('Ahmad Fauzi')
            ->assertSee('1/2')->assertSee('Hadir 1')->assertSee('Izin 1');
    }
    public function test_bulan_di_luar_tahun_ajaran_menampilkan_pesan_error(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '');
    }
    public function test_wali_dengan_dua_kelas_menampilkan_pemilih_kelas(): void
    {
        $s = RecapScenario::waliKelas();
        $kedua = RecapScenario::kelasLain($s, 'VII-B', $s->wali);
        RecapScenario::siswaDi($kedua, 'Budi Santoso');
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Semua kelas')->assertSee('VII-B')
            ->set('classId', $kedua->id)
            ->assertSee('Budi Santoso')->assertDontSee('Ahmad Fauzi');
    }
    public function test_guru_bukan_wali_tidak_lihat_tab_kelas_saya(): void
    {
        RecapScenario::waliKelas();
        $this->actingAs(User::factory()->guru()->create())->get(route('rekap.index'))
            ->assertOk()->assertDontSee('Kelas Saya');
    }
}
```
Test periode tidak menguji kalimat persis dari `RecapPeriodOutOfRange` karena teks itu milik Plan 1; yang diuji di sini adalah halaman menangkap exception itu dan menampilkannya sebagai pesan, bukan crash.
- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=HomeroomClassRecapTest
```
Expected: FAIL — `Class "App\Livewire\HomeroomClassRecap" not found`.

- [ ] **Step 3: Tulis trait, component, partial, view, dan rute**

`app/Livewire/Concerns/InteractsWithRecapPeriod.php`:
```php
<?php
namespace App\Livewire\Concerns;
use App\Models\AcademicYear;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
trait InteractsWithRecapPeriod
{
    public string $academicYearId = '';
    public string $periodType = 'monthly';
    public string $month = '';
    public string $date = '';
    public ?string $periodError = null;
    protected function initializeRecap(): void
    {
        $tahun = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $this->academicYearId = (string) ($tahun?->id ?? '');
        $this->month = now()->format('Y-m');
        $this->date = now()->toDateString();
    }
    protected function recapRules(): array
    {
        return [
            'academicYearId' => ['required', 'uuid', 'exists:academic_years,id'],
            'periodType' => ['required', 'in:daily,monthly'],
            'month' => ['required', 'format:Y-m'],
            'date' => ['required', 'format:Y-m-d'],
        ];
    }
    protected function yearOptions(): Collection
    {
        return AcademicYear::query()->orderByDesc('start_date')->get();
    }
    protected function year(): ?AcademicYear
    {
        return AcademicYear::find($this->academicYearId)
            ?? AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
    }
    protected function period(?AcademicYear $year): ?RecapPeriod
    {
        if ($year === null) {
            $this->periodError = null;
            return null;
        }
        try {
            $periode = $this->periodType === 'daily'
                ? RecapPeriod::daily(Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay())
                : RecapPeriod::monthly((int) substr($this->month, 0, 4), (int) substr($this->month, 5, 2));
            $periode->assertWithin($year);
        } catch (RecapPeriodOutOfRange $exception) {
            $this->periodError = $exception->getMessage();
            return null;
        }
        $this->periodError = null;
        return $periode;
    }
}
```
`RecapPeriodOutOfRange` adalah kasus input normal — pengguna salah pilih bulan — jadi exception itu ditangkap dan jadi pesan, bukan halaman 500.

`app/Recap/RecapQueries/HomeroomClassRecapQuery.php` — tambahkan properti dan method ini:
```php
    private ?SchoolClass $schoolClass = null;
    public function forClass(SchoolClass $schoolClass): self
    {
        $this->schoolClass = $schoolClass;
        return $this;
    }
    /** Daftar kelas yang diwalikan, untuk pemilih kelas dan deteksi empty state. */
    public function ownedClasses(User $actor, AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->where('homeroom_teacher_id', $actor->id)
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('name')
            ->get();
    }
```
`run()` ditulis ulang seluruh isinya menjadi:
```php
    public function run(RecapPeriod $period): RecapResult
    {
        if ($this->actor === null || $this->academicYear === null) {
            throw new LogicException('Panggil for() dengan user dan tahun ajaran sebelum run().');
        }
        $period->assertWithin($this->academicYear);
        if ($this->schoolClass !== null
            && ($this->schoolClass->homeroom_teacher_id !== $this->actor->id
                || $this->schoolClass->academic_year_id !== $this->academicYear->id)) {
            throw new LogicException('Kelas yang dipilih bukan kelas yang diwalikan pada tahun ajaran ini.');
        }
        $kelas = SchoolClass::query()
            ->where('homeroom_teacher_id', $this->actor->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->when($this->schoolClass, fn ($query) => $query->whereKey($this->schoolClass->id))
            ->with('students')
            ->orderBy('name')
            ->get();
        $jadwal = Schedule::query()
            ->whereIn('class_id', $kelas->pluck('id'))
            ->with('subject')
            ->get();
        return $this->buildResult($kelas, $jadwal, $period, $kelas->flatMap(fn (SchoolClass $item) => $item->students));
    }
```
Pemeriksaan kepemilikan kelas diulang di `run()` walau `SchoolClassPolicy::viewRecap` sudah menjaganya: query object adalah tempat terakhir sebelum data dibaca, dan pemanggil lain bisa memakai class ini tanpa policy. Batas tahun ajaran tetap dijaga filter `academic_year_id` di query `SchoolClass`, jadi kelas dari tahun lain tidak bisa terbawa oleh `forClass()`.

`app/Livewire/HomeroomClassRecap.php`:
```php
<?php
namespace App\Livewire;
use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\SchoolClass;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\HomeroomClassRecapQuery;
use App\Recap\RecapResult;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('layouts.app')]
class HomeroomClassRecap extends Component
{
    use InteractsWithRecapPeriod;
    public ?string $classId = null;
    public ?string $studentId = null;
    private HomeroomClassRecapQuery $query;
    protected function rules(): array
    {
        return [...$this->recapRules(), 'classId' => ['nullable', 'uuid']];
    }
    public function mount(): void
    {
        $this->initializeRecap();
    }
    public function updatedAcademicYearId(): void
    {
        $this->classId = null;
        $this->studentId = null;
    }
    public function lihatSiswa(string $studentId): void
    {
        $this->studentId = $studentId;
    }
    public function tutupRiwayat(): void
    {
        $this->studentId = null;
    }
    public function render()
    {
        $this->query = app(HomeroomClassRecapQuery::class);
        $tahun = $this->year();
        $periode = $this->period($tahun);
        return view('livewire.homeroom-class-recap', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'daftarKelas' => $tahun === null ? collect() : $this->query->ownedClasses(auth()->user(), $tahun),
            'result' => $tahun === null || $periode === null ? null : $this->rekap($periode),
        ]);
    }
    private function rekap(RecapPeriod $periode): RecapResult
    {
        $this->query->for(auth()->user(), $this->year());
        if ($this->classId !== null) {
            $kelas = SchoolClass::find($this->classId) ?? abort(404, 'Kelas tidak ditemukan.');
            Gate::authorize('viewRecap', $kelas);
            $this->query->forClass($kelas);
        }
        return $this->query->run($periode);
    }
}
```
`resources/views/recap/partials/matrix.blade.php` — satu-satunya tempat matriks dirender, dipakai ketiga halaman dan PDF:
```blade
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b">
                <th class="px-2 py-1 text-left">No</th>
                <th class="px-2 py-1 text-left">NISN</th>
                <th class="px-2 py-1 text-left">Nama Siswa</th>
                <th class="px-2 py-1 text-left">Kelas</th>
                @foreach ($result->subjects as $mapel)<th class="px-2 py-1">{{ $mapel->name }}</th>@endforeach
                <th class="px-2 py-1">Hadir</th>
                <th class="px-2 py-1">Izin</th>
                <th class="px-2 py-1">Sakit</th>
                <th class="px-2 py-1">Alpa</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $index => $row)
                <tr class="border-b hover:bg-zinc-50">
                    <td class="px-2 py-1">{{ $index + 1 }}</td>
                    <td class="px-2 py-1">{{ $row['student']->nisn }}</td>
                    <td class="px-2 py-1">@if (isset($rowUrl))<button type="button" wire:click="{{ $rowUrl($row['student']) }}" class="text-blue-700 underline">{{ $row['student']->name }}</button>@else{{ $row['student']->name }}@endif</td>
                    <td class="px-2 py-1">{{ $row['class']->name }}</td>
                    @foreach ($result->subjects as $mapel)
                        @php $cell = $row['cells'][$mapel->id] ?? null; @endphp
                        <td class="px-2 py-1">{{ $cell !== null && $cell['total'] > 0 ? $cell['hadir'].'/'.$cell['total'] : '-' }}</td>
                    @endforeach
                    <td class="px-2 py-1">{{ $row['total']['hadir'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['izin'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['sakit'] }}</td>
                    <td class="px-2 py-1">{{ $row['total']['alpa'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 4 + $result->subjects->count() + 4 }}" class="px-2 py-3 text-center text-zinc-500">Tidak ada data absensi pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3 text-sm text-zinc-700">Ringkasan — Hadir {{ $result->summary['hadir'] }}, Izin {{ $result->summary['izin'] }}, Sakit {{ $result->summary['sakit'] }}, Alpa {{ $result->summary['alpa'] }}</div>
```
Kolom "Kelas" sengaja tetap ada di semua halaman: matriks guru dan laporan admin sudah diurutkan per kelas, jadi nama kelas di setiap baris adalah konteks yang dibutuhkan pembaca.

`resources/views/recap/partials/filter-periode.blade.php` — set `$tampilkanHarian` ke `false` dari halaman yang hanya bulanan:
```blade
<label class="text-sm text-zinc-700">Tahun ajaran
    <select wire:model.live="academicYearId" class="mt-1 block rounded-md border-zinc-300 text-sm">
        @foreach ($daftarTahun as $tahun)<option value="{{ $tahun->id }}">{{ $tahun->name }}</option>@endforeach
    </select>
</label>
@if ($tampilkanHarian ?? true)
    <label class="text-sm text-zinc-700">Jenis periode
        <select wire:model.live="periodType" class="mt-1 block rounded-md border-zinc-300 text-sm">
            <option value="monthly">Bulanan</option>
            <option value="daily">Harian</option>
        </select>
    </label>
@endif
@if ($periodType === 'monthly')
    <label class="text-sm text-zinc-700">Bulan
        <input type="month" wire:model.live="month" class="mt-1 block rounded-md border-zinc-300 text-sm">
    </label>
@else
    <label class="text-sm text-zinc-700">Tanggal
        <input type="date" wire:model.live="date" class="mt-1 block rounded-md border-zinc-300 text-sm">
    </label>
@endif
```
`resources/views/livewire/homeroom-class-recap.blade.php`:
```blade
<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Kelas Saya</h2>
    @if ($periodError)<div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $periodError }}</div>@endif
    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode')
            @if ($daftarKelas->count() > 1)
                <label class="text-sm text-zinc-700">Pilih kelas
                    <select wire:model.live="classId" class="mt-1 block rounded-md border-zinc-300 text-sm">
                        <option value="">Semua kelas</option>
                        @foreach ($daftarKelas as $kelas)<option value="{{ $kelas->id }}">{{ $kelas->name }}</option>@endforeach
                    </select>
                </label>
            @endif
        </div>
        @if ($result === null)
            <p class="text-sm text-zinc-600">Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($daftarKelas->isEmpty())
            <p class="text-sm text-zinc-600">Kamu belum diwalikan kelas. Hubungi admin.</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>
```
`resources/views/recap/index.blade.php` — shell tab rekap. Daftar tab datang dari view composer, dan setiap task berikutnya menambah satu tab tanpa menyentuh file ini lagi:
```blade
<x-layouts.app title="Rekap">
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-2 border-b border-zinc-200 text-sm">
            <a href="{{ route('jadwal.index') }}" class="px-3 py-2 text-zinc-600">Jadwal Saya</a>
            @foreach ($tabRekap as $tab)<span class="border-b-2 border-blue-600 px-3 py-2 font-semibold text-blue-700">{{ $tab }}</span>@endforeach
        </nav>
        @livewire('homeroom-class-recap')
    </div>
</x-layouts.app>
```

Layout `layouts/app.blade.php` milik starter kit hanya menerima prop `title` dan sebuah slot default. Slot bernama `header` tidak dibaca wrapper itu, jadi `<x-slot name="header">` akan hilang tanpa error. Karena itu judul ditulis sebagai `title="Rekap"`, bukan lewat slot.
`app/Providers/AppServiceProvider.php` — di dalam `boot()` yang sudah ada, tambahkan `use Illuminate\Support\Facades\View;` di bagian atas file dan baris ini di dalam `boot()`:
```php
        View::composer('recap.index', function ($view): void {
            $user = auth()->user();
            $view->with('tabRekap', $user?->homeroomClasses()->exists() === true ? ['Kelas Saya'] : []);
        });
```
Tab "Kelas Saya" hilang **sama sekali** untuk guru yang bukan wali kelas (spes §5.4), dan penentuannya relasi `homeroomClasses()`, bukan kolom `role`.

`routes/web.php` — sisipkan `use App\Livewire\HomeroomClassRecap;` di bagian atas file, lalu di dalam grup `Route::middleware('auth')->group(...)` dari Plan 2:
```php
    Route::view('/rekap', 'recap.index')->name('recap.index');
```
- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=HomeroomClassRecapTest
```
**Expected: 4 tests pass**

Daftar kelas untuk pemilih diambil dari `ownedClasses()`, bukan dari `$result->classes` — kalau dari hasil, pemilih kelas akan berisi satu pilihan saja begitu satu kelas dipilih.

## Task 2: Drill-down riwayat siswa

- Modify: `app/Recap/RecapQueries/HomeroomClassRecapQuery.php` (tambah `studentHistory()`)
- Modify: `app/Livewire/HomeroomClassRecap.php` (tambah `statusLabel()` dan dua helper privat)
- Modify: `resources/views/livewire/homeroom-class-recap.blade.php` (sisipkan panel riwayat)
- Test: `tests/Feature/Livewire/HomeroomClassRecapDrilldownTest.php`

Matriks yang dikembalikan query object hanya berisi angka agregat per mata pelajaran, jadi status per tanggal tidak ada di sana. Satu query kedua memang dibutuhkan — dan karena scoping adalah tanggung jawab query object, method itu **ditambahkan pada `HomeroomClassRecapQuery`**, bukan ditulis di component dan bukan menjadi file query baru. Otorisasi drill-down mengikuti scope query itu, bukan `StudentPolicy::view`: wali kelas memantau siswa kelasnya lintas semua mata pelajaran (spes §4), sedangkan policy itu hanya mencakup siswa di kelas yang dia ajar.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/HomeroomClassRecapDrilldownTest.php`:
```php
<?php
namespace Tests\Feature\Livewire;
use App\Enums\AttendanceStatus;
use App\Livewire\HomeroomClassRecap;
use App\Models\{SchoolClass, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class HomeroomClassRecapDrilldownTest extends TestCase
{
    use RefreshDatabase;
    public function test_membuka_siswa_menampilkan_status_per_tanggal(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)
            ->assertSee('Riwayat Absensi')->assertSee('1 September 2025')
            ->assertSee('Matematika')->assertSee('Izin')->assertSee('Surat dokter');
    }
    public function test_matriks_tidak_membocorkan_siswa_kelas_lain(): void
    {
        $s = RecapScenario::waliKelas();
        RecapScenario::siswaDi($s->kelas, 'Siti Rahma');
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Ahmad Fauzi')->assertDontSee('Siti Rahma');
    }
    public function test_wali_tidak_bisa_membuka_siswa_dari_kelas_lain(): void
    {
        $s = RecapScenario::waliKelas();
        $luar = RecapScenario::siswaDi(SchoolClass::factory()->create(['academic_year_id' => $s->tahun->id, 'name' => 'IX-B']), 'Siti Rahma');
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $luar->id)
            ->assertSee('Riwayat siswa tidak tersedia.')->assertDontSee('Surat dokter');
    }
    public function test_riwayat_mencakup_mapel_guru_lain(): void
    {
        $s = RecapScenario::waliKelas();
        $guruLain = User::factory()->guru()->create();
        RecapScenario::absen($s->siswa, RecapScenario::mapelLain($s, 'Fisika', $guruLain), '2025-09-03', AttendanceStatus::Hadir, null, $guruLain);
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)
            ->assertSee('Fisika')->assertSee('Hadir');
    }
    public function test_menutup_riwayat_kembali_ke_matriks(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)->assertSet('studentId', $s->siswa->id)
            ->call('tutupRiwayat')->assertSet('studentId', null)
            ->assertDontSee('Riwayat Absensi');
    }
}
```
- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=HomeroomClassRecapDrilldownTest
```
Expected: FAIL — `Call to undefined method App\Livewire\HomeroomClassRecap::lihatSiswa()`.

- [ ] **Step 3: Tulis query, component, dan panel riwayat**

`app/Recap/RecapQueries/HomeroomClassRecapQuery.php` — tambahkan `use App\Models\Student;`, `use Illuminate\Support\Collection;`, dan `use Illuminate\Support\Facades\DB;` bila belum ada, lalu method ini:
```php
    /**
     * Riwayat harian seorang siswa untuk satu periode, hanya dari kelas yang diwalikan
     * $actor pada $academicYear. Siswa dari kelas lain menghasilkan collection kosong.
     */
    public function studentHistory(User $actor, AcademicYear $academicYear, Student $student, RecapPeriod $period): Collection
    {
        $kelasIds = SchoolClass::query()
            ->where('homeroom_teacher_id', $actor->id)
            ->where('academic_year_id', $academicYear->id)
            ->whereKey($student->class_id)
            ->pluck('id');
        $jadwalIds = $kelasIds->isEmpty()
            ? collect()
            : Schedule::query()->whereIn('class_id', $kelasIds)->pluck('id');
        if ($jadwalIds->isEmpty()) {
            return collect();
        }
        $period->assertWithin($academicYear);
        return DB::table('attendances')
            ->join('schedules', 'schedules.id', '=', 'attendances.schedule_id')
            ->join('subjects', 'subjects.id', '=', 'schedules.subject_id')
            ->where('attendances.student_id', $student->id)
            ->whereIn('attendances.schedule_id', $jadwalIds)
            ->whereBetween('attendances.date', [$period->start->toDateString(), $period->end->toDateString()])
            ->orderBy('attendances.date')
            ->orderBy('subjects.name')
            ->get(['attendances.date', 'subjects.name as mapel', 'attendances.status', 'attendances.note'])
            ->map(fn ($baris) => [
                'date' => $baris->date,
                'mapel' => $baris->mapel,
                'status' => $baris->status,
                'note' => $baris->note,
            ]);
    }
```
`DB::table` dipakai supaya `status` tetap string biasa; lewat model `Attendance` nilainya jadi enum dan `match` di component harus memakai enum. Query ini tetap satu-satunya tempat yang tahu siswa mana milik `actor` — component tidak melakukan query apa pun.

`app/Livewire/HomeroomClassRecap.php` — ganti array di dalam `return view(...)` menjadi:
```php
        return view('livewire.homeroom-class-recap', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'daftarKelas' => $tahun === null ? collect() : $this->query->ownedClasses(auth()->user(), $tahun),
            'result' => $tahun === null || $periode === null ? null : $this->rekap($periode),
            'siswaDipilih' => $this->siswaDipilih($tahun),
            'riwayat' => $this->studentId === null ? collect() : $this->riwayat($tahun, $periode),
        ]);
```
Tambahkan tiga method ini, dan sisipkan `use App\Models\AcademicYear;`, `use App\Models\SchoolClass;`, `use App\Models\Student;`, dan `use Illuminate\Support\Collection;` di bagian atas file:
```php
    public function statusLabel(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            default => 'Alpa',
        };
    }
    private function siswaDipilih(?AcademicYear $tahun): ?Student
    {
        if ($tahun === null || $this->studentId === null) {
            return null;
        }
        $siswa = Student::find($this->studentId);
        $milik = $siswa !== null && $this->query->ownedClasses(auth()->user(), $tahun)->contains('id', $siswa->class_id);
        return $milik ? $siswa : null;
    }
    private function riwayat(?AcademicYear $tahun, ?RecapPeriod $periode): Collection
    {
        if ($tahun === null || $periode === null) {
            return collect();
        }
        $siswa = $this->siswaDipilih($tahun);
        return $siswa === null ? collect() : $this->query->studentHistory(auth()->user(), $tahun, $siswa, $periode);
    }
```
`resources/views/livewire/homeroom-class-recap.blade.php` — di cabang `@else` yang sudah menaruh matriks, sisipkan blok ini tepat sebelum `@include('recap.partials.matrix', ...)`:
```blade
            @php $rowUrl = fn ($siswa) => "lihatSiswa('{$siswa->id}')"; @endphp
            @if ($studentId !== null)
                <div class="rounded-lg border border-zinc-200 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-zinc-900">Riwayat Absensi — {{ $siswaDipilih?->name ?? 'Siswa' }}</h3>
                        <button type="button" wire:click="tutupRiwayat" class="rounded-md border border-zinc-300 bg-white px-3 py-1 text-sm">Tutup</button>
                    </div>
                    @if ($riwayat->isEmpty())
                        <p class="text-sm text-zinc-600">Riwayat siswa tidak tersedia.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead><tr class="border-b"><th class="px-2 py-1 text-left">Tanggal</th><th class="px-2 py-1 text-left">Mata Pelajaran</th><th class="px-2 py-1 text-left">Status</th><th class="px-2 py-1 text-left">Alasan</th></tr></thead>
                            <tbody>
                                @foreach ($riwayat as $baris)
                                    <tr class="border-b">
                                        <td class="px-2 py-1">{{ \Illuminate\Support\Carbon::parse($baris['date'])->translatedFormat('d F Y') }}</td>
                                        <td class="px-2 py-1">{{ $baris['mapel'] }}</td>
                                        <td class="px-2 py-1">{{ $this->statusLabel($baris['status']) }}</td>
                                        <td class="px-2 py-1">{{ $baris['note'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endif
```
`$rowUrl` hanya ada di halaman ini; partial matriks memakainya dengan `isset($rowUrl)`, jadi `TeacherSubjectRecap` dan `AdminAttendanceReport` tetap merender nama sebagai teks biasa.

- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=HomeroomClassRecapDrilldownTest
```
**Expected: 5 tests pass**

`studentId` datang dari browser dan tidak pernah dipercaya: kelas siswa dicek terhadap daftar kelas yang diwalikan `actor` sebelum satu baris pun dibaca.

## Task 3: `TeacherSubjectRecap`

- Create: `app/Livewire/TeacherSubjectRecap.php`
- Create: `resources/views/livewire/teacher-subject-recap.blade.php`
- Modify: `routes/web.php` (tambah `/rekap/mapel-saya`)
- Modify: `app/Providers/AppServiceProvider.php` (tab "Mapel Saya")
- Test: `tests/Feature/Livewire/TeacherSubjectRecapTest.php`

Kolom matriks berisi mapel **yang guru itu ajar**. Guru yang mengajar Matematika di VII-A dan Fisika di IX-B mendapat satu tabel dengan kolom keduanya, dan tiap baris membawa `class` sendiri sehingga tidak ada data yang tercampur. `TeacherSubjectRecapQuery` (Plan 1 Task 7) sudah melakukan itu, jadi halaman ini tidak pernah memanggil `forClass()`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/TeacherSubjectRecapTest.php`:
```php
<?php
namespace Tests\Feature\Livewire;
use App\Livewire\TeacherSubjectRecap;
use App\Models\{Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class TeacherSubjectRecapTest extends TestCase
{
    use RefreshDatabase;
    public function test_menampilkan_mapel_dan_siswa_yang_diajar(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Matematika')->assertSee('Ahmad Fauzi')->assertSee('1/2');
    }
    public function test_mapel_guru_lain_tidak_muncul(): void
    {
        $s = RecapScenario::waliKelas();
        RecapScenario::mapelLain($s, 'Fisika', User::factory()->guru()->create());
        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Matematika')->assertDontSee('Fisika');
    }
    public function test_guru_tanpa_jadwal_menampilkan_pesan_kosong(): void
    {
        RecapScenario::waliKelas();
        Schedule::query()->delete();
        Livewire::actingAs(User::factory()->guru()->create())->test(TeacherSubjectRecap::class)
            ->assertSee('Belum ada jadwal mengajar pada tahun ajaran ini.');
    }
    public function test_hanya_menyediakan_filter_bulanan(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Bulan')->assertDontSee('Harian');
    }
}
```
- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=TeacherSubjectRecapTest
```
Expected: FAIL — `Class "App\Livewire\TeacherSubjectRecap" not found`.

- [ ] **Step 3: Tulis component, view, dan rute**

`app/Livewire/TeacherSubjectRecap.php`:
```php
<?php
namespace App\Livewire;
use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\TeacherSubjectRecapQuery;
use App\Recap\RecapResult;
use Livewire\Attributes\Layout;
use Livewire\Component;
#[Layout('layouts.app')]
class TeacherSubjectRecap extends Component
{
    use InteractsWithRecapPeriod;
    protected function rules(): array
    {
        return $this->recapRules();
    }
    public function mount(): void
    {
        $this->initializeRecap();
        $this->periodType = 'monthly';
    }
    public function render()
    {
        $tahun = $this->year();
        $periode = $this->period($tahun);
        return view('livewire.teacher-subject-recap', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'result' => $this->rekap($tahun, $periode),
        ]);
    }
    private function rekap(?AcademicYear $tahun, ?RecapPeriod $periode): ?RecapResult
    {
        if ($tahun === null || $periode === null) {
            return null;
        }
        return app(TeacherSubjectRecapQuery::class)->for(auth()->user(), $tahun)->run($periode);
    }
}
```
`periodType` dikunci ke `'monthly'` di `mount()` dan tidak pernah diubah halaman ini, jadi yang dipakai selalu `RecapPeriod::monthly()`. Kolom "Jenis periode" disembunyikan lewat partial filter karena rekap mapel harian bukan kebutuhan yang disebut di spes §5.1. Nilai dikunci lewat `mount()`, bukan lewat Blade, supaya request dari browser yang mengubah `periodType` tidak bisa membuka periode harian di halaman yang tidak mendukungnya.

`resources/views/livewire/teacher-subject-recap.blade.php`:
```blade
<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Mata Pelajaran Saya</h2>
    @include('recap.partials.periode-error')
    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end gap-3">
            @include('recap.partials.filter-periode', ['tampilkanHarian' => false])
        </div>
        @if ($result === null)
            <p class="text-sm text-zinc-600">Pilih bulan yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600">Belum ada jadwal mengajar pada tahun ajaran ini.</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>
```
`routes/web.php` — tambahkan `use App\Livewire\TeacherSubjectRecap;` lalu sisipkan di dalam grup `auth`:
```php
    Route::get('/rekap/mapel-saya', TeacherSubjectRecap::class)->name('rekap.mapel-saya');
```
`app/Providers/AppServiceProvider.php` — ubah array tab di view composer menjadi:
```php
            $view->with('tabRekap', array_values(array_filter([
                $user?->homeroomClasses()->exists() === true ? 'Kelas Saya' : null,
                'Mapel Saya',
            ])));
```
Tab "Mapel Saya" selalu tampil untuk guru mana pun yang punya akun, karena setiap guru mengajar minimal satu mapel. Penentuannya bukan `role`, jadi admin yang membuka `/rekap` melihat tab yang sama tanpa error.

- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=TeacherSubjectRecapTest
```
**Expected: 4 tests pass**

Halaman ini tidak memakai pemilih kelas: `TeacherSubjectRecapQuery` menggabungkan semua kelas yang diajar menjadi satu tabel dan setiap baris sudah membawa `class`-nya sendiri.

## Task 4: Pesan periode di ketiga halaman

- Create: `resources/views/recap/partials/periode-error.blade.php`
- Modify: `resources/views/livewire/homeroom-class-recap.blade.php` (pakai partial pesan)
- Modify: `resources/views/livewire/teacher-subject-recap.blade.php` (pakai partial pesan)
- Test: `tests/Feature/Livewire/RecapPeriodErrorTest.php`

Blok pesan periode yang disalin di tiap halaman harus diubah di banyak tempat saat teks atau gaya berubah. Partial ini juga memberi satu `role="alert"` yang konsisten, supaya pembaca layar membaca pesan itu saat halaman dimuat. Halaman admin belum ada pada task ini; perilakunya diuji di Task 5 setelah component-nya ada, karena test yang menunjuk class yang belum ada hanya menghasilkan error yang tidak bisa dibaca.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/RecapPeriodErrorTest.php`:
```php
<?php
namespace Tests\Feature\Livewire;
use App\Livewire\{HomeroomClassRecap, TeacherSubjectRecap};
use App\Recap\{RecapPeriod, RecapPeriodOutOfRange};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class RecapPeriodErrorTest extends TestCase
{
    use RefreshDatabase;
    public function test_wali_kelas_menampilkan_pesan_periode(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '')
            ->assertSeeHtml('role="alert"');
    }
    public function test_guru_mapel_menampilkan_pesan_periode(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '')
            ->assertSeeHtml('role="alert"');
    }
    public function test_tabel_tidak_dirender_saat_periode_tidak_valid(): void
    {
        $s = RecapScenario::waliKelas();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Ahmad Fauzi')
            ->set('month', '2025-07')
            ->assertDontSee('Ahmad Fauzi')->assertSet('result', null);
    }
    public function test_pesan_menyalin_teks_exception_asli(): void
    {
        $s = RecapScenario::waliKelas();
        try {
            RecapPeriod::monthly(2025, 7)->assertWithin($s->tahun);
            $this->fail('RecapPeriod::monthly(2025, 7) seharusnya berada di luar tahun ajaran.');
        } catch (RecapPeriodOutOfRange $exception) {
            $pesan = $exception->getMessage();
        }
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', $pesan);
    }
}
```
Test terakhir mengambil kalimat dari exception yang benar-benar dilempar, bukan menyalin string di sini: kalau Plan 1 mengganti wording-nya test tetap benar, dan kalau halaman menulis ulang kalimatnya sendiri test ini gagal.

- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=RecapPeriodErrorTest
```
Expected: FAIL — `assertSeeHtml('role="alert"')` gagal karena blok pesan masih inline tanpa atribut itu.

- [ ] **Step 3: Tulis partial dan pakai di kedua view**

`resources/views/recap/partials/periode-error.blade.php`:
```blade
@if ($periodError)
    <div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $periodError }}</div>
@endif
```
Di `resources/views/livewire/homeroom-class-recap.blade.php` dan `resources/views/livewire/teacher-subject-recap.blade.php`, ganti baris `@if ($periodError)<div class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $periodError }}</div>@endif` dengan:
```blade
    @include('recap.partials.periode-error')
```
- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=RecapPeriodErrorTest
```
**Expected: 4 tests pass**

## Task 5: `AdminAttendanceReport` dan export Excel

- Create: `app/Livewire/AdminAttendanceReport.php`
- Create: `resources/views/livewire/admin-attendance-report.blade.php`
- Modify: `routes/web.php` (tambah `/rekap/admin`)
- Modify: `app/Providers/AppServiceProvider.php` (tab "Semua Absensi")
- Test: `tests/Feature/Livewire/AdminAttendanceReportTest.php`


Admin satu-satunya role yang boleh melihat semua kelas sekaligus, jadi gate `viewAnyRecap` dipasang di `mount()` — bukan di view dan bukan hanya lewat menyembunyikan tab. Query-nya sudah scoped sendiri; gate di component menutup jalur HTTP. Halaman ini full-page component, jadi tidak perlu file blade shell seperti `recap/index.blade.php`.

- [ ] **Step 1: Tulis test yang gagal**
```php
<?php
namespace Tests\Feature\Livewire;
use App\Livewire\AdminAttendanceReport;
use App\Models\{AcademicYear, Attendance, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class AdminAttendanceReportTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_melihat_seluruh_kelas(): void
    {
        $s = RecapScenario::waliKelas();
        $lain = RecapScenario::kelasLain($s, 'IX-B', User::factory()->guru()->create());
        RecapScenario::siswaDi($lain, 'Siti Rahma');
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('VII-A')->assertSee('Ahmad Fauzi')
            ->assertSee('IX-B')->assertSee('Siti Rahma');
    }
    public function test_guru_ditolak_membuka_laporan_admin(): void
    {
        $s = RecapScenario::waliKelas();
        $this->actingAs($s->wali)->get(route('rekap.admin'))->assertForbidden();
    }
    public function test_tahun_ajaran_kosong_menampilkan_pesan(): void
    {
        RecapScenario::waliKelas();
        AcademicYear::query()->delete();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.');
    }
    public function test_tidak_ada_absensi_menampilkan_pesan(): void
    {
        RecapScenario::waliKelas();
        Attendance::query()->delete();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');
    }
    public function test_export_excel_mengunduh_file(): void
    {
        RecapScenario::waliKelas();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->call('exportExcel')
            ->assertFileDownloaded('rekap-absensi-2025-09.xlsx');
    }
    public function test_export_ditolak_saat_periode_tidak_valid(): void
    {
        RecapScenario::waliKelas();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-07')
            ->call('exportExcel')
            ->assertStatus(422);
    }
}
```
Dua test terakhir mengunci dua aturan yang mudah bocor: nama file harus menyebut periode agar arsip tidak menimpa, dan export tidak boleh berjalan saat periode di luar tahun ajaran. Hentikan dengan `abort(422)`, jangan ekspor tabel kosong yang terlihat benar.

- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=AdminAttendanceReportTest
```
Expected: FAIL — `Class "App\Livewire\AdminAttendanceReport" not found`.

- [ ] **Step 3: Tulis component, view, tab, dan rute**

`app/Livewire/AdminAttendanceReport.php`:
```php
<?php
namespace App\Livewire;
use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Recap\RecapExporter;
use App\Recap\RecapPeriod;
use App\Recap\RecapQueries\AdminRecapQuery;
use App\Recap\RecapResult;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
#[Layout('layouts.app')]
class AdminAttendanceReport extends Component
{
    use InteractsWithRecapPeriod;
    protected function rules(): array
    {
        return $this->recapRules();
    }
    public function mount(): void
    {
        Gate::authorize('viewAnyRecap');
        $this->initializeRecap();
    }
    public function render()
    {
        $tahun = $this->year();
        $periode = $this->period($tahun);
        return view('livewire.admin-attendance-report', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'result' => $this->rekap($tahun, $periode),
        ]);
    }
    public function exportExcel(): StreamedResponse
    {
        return (new RecapExporter($this->hasilEkspor()))->toExcel();
    }
    private function hasilEkspor(): RecapResult
    {
        $hasil = $this->rekap($this->year(), $this->period($this->year()));
        if ($hasil === null) {
            abort(422, 'Periode tidak valid, tidak ada data yang bisa diekspor.');
        }
        return $hasil;
    }
    private function rekap(?AcademicYear $tahun, ?RecapPeriod $periode): ?RecapResult
    {
        if ($tahun === null || $periode === null) {
            return null;
        }
        return app(AdminRecapQuery::class)
            ->for(auth()->user(), $tahun)
            ->run($periode);
    }
}
```
`exportExcel()` membangun ulang `RecapResult` lewat query yang sama dengan `render()`, jadi angka di file Excel dijamin sama dengan angka di layar. `abort(422)` dipakai, bukan pesan di layar, karena tidak ada file yang boleh diunduh. Task 6 menambah `exportPdf()` dengan pola `hasilEkspor()` yang sama persis.

`resources/views/livewire/admin-attendance-report.blade.php`:
```blade
<div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
    <h2 class="text-lg font-semibold text-zinc-900">Rekap Semua Absensi</h2>
    @include('recap.partials.periode-error')
    @if (! $tahunAjaran)
        <p class="text-sm text-zinc-600">Belum ada tahun ajaran. Hubungi admin.</p>
    @else
        <div class="flex flex-wrap items-end justify-between gap-3">
            @include('recap.partials.filter-periode')
            <button type="button" wire:click="exportExcel" class="rounded-md bg-zinc-900 px-3 py-2 text-sm text-white">Unduh Excel</button>
        </div>
        @if ($result === null)
            <p class="text-sm text-zinc-600">Pilih periode yang berada di dalam tahun ajaran {{ $tahunAjaran->name }}.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600">Tidak ada kelas pada tahun ajaran ini.</p>
        @else
            @include('recap.partials.matrix', ['result' => $result])
        @endif
    @endif
</div>
```
Kalimat "Tidak ada data absensi pada periode ini." milik partial matriks dan dipakai ulang apa adanya di view ini — mengulang kalimat yang sama di dua tempat berakhir jadi kalimat yang berbeda di salah satunya.

`routes/web.php` — tambahkan `use App\Livewire\AdminAttendanceReport;` lalu sisipkan di dalam grup `auth`:
```php
    Route::get('/rekap/admin', AdminAttendanceReport::class)->name('rekap.admin');
```
`app/Providers/AppServiceProvider.php` — ubah array tab di view composer menjadi:
```php
            $view->with('tabRekap', array_values(array_filter([
                $user?->homeroomClasses()->exists() === true ? 'Kelas Saya' : null,
                'Mapel Saya',
                $user?->isAdmin() === true ? 'Semua Absensi' : null,
            ])));
```
- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=AdminAttendanceReportTest
```
**Expected: 6 tests pass**

## Task 6: Export PDF

- Modify: `composer.json` (tambah `barryvdh/laravel-dompdf`)
- Modify: `app/Recap/RecapExporter.php` (tambah `toPdf()`)
- Modify: `app/Livewire/AdminAttendanceReport.php` (tambah `exportPdf()`)
- Modify: `resources/views/livewire/admin-attendance-report.blade.php` (tambah tombol PDF)
- Create: `resources/views/recap/pdf.blade.php`
- Test: `tests/Feature/Recap/RecapExporterPdfTest.php`

Laravel Excel tidak bisa menghasilkan PDF, jadi PDF memakai dompdf — paket PHP murni, tanpa binary eksternal yang bisa gagal di shared hosting.
```powershell
composer require barryvdh/laravel-dompdf
```
Kalau resolver Composer tidak menemukan versi yang cocok dengan gate Laravel Plan 1, ambil versi tertinggi yang constraint `illuminate/support`-nya mengizinkan major tersebut. Jangan mengubah gate di `composer.json` demi memaksa satu paket terpasang.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Recap/RecapExporterPdfTest.php`:
```php
<?php
namespace Tests\Feature\Recap;
use App\Livewire\AdminAttendanceReport;
use App\Models\User;
use App\Recap\{RecapPeriod, RecapQueries\AdminRecapQuery};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class RecapExporterPdfTest extends TestCase
{
    use RefreshDatabase;
    public function test_pdf_mengunduh_file(): void
    {
        RecapScenario::waliKelas();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->call('exportPdf')->assertFileDownloaded('rekap-absensi-2025-09.pdf');
    }
    public function test_isi_pdf_menyalin_angka_dari_recap_result(): void
    {
        $s = RecapScenario::waliKelas();
        $hasil = app(AdminRecapQuery::class)->for(User::factory()->admin()->create(), $s->tahun)->run(RecapPeriod::monthly(2025, 9));
        $html = view('recap.pdf', ['result' => $hasil, 'periode' => 'September 2025'])->render();
        $this->assertStringContainsString('Ahmad Fauzi', $html);
        $this->assertStringContainsString('Hadir 1', $html);
        $this->assertStringContainsString('Izin 1', $html);
    }
    public function test_pdf_ditolak_saat_periode_tidak_valid(): void
    {
        RecapScenario::waliKelas();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-07')->call('exportPdf')->assertStatus(422);
    }
}
```
Test kedua menguji isi PDF tanpa membuka file biner: view `recap.pdf` dirender langsung dengan `RecapResult` yang sama. Kalau angka di PDF berbeda dari layar, angka itu bukan dari `RecapResult` dan test ini pecah.

- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=RecapExporterPdfTest
```
Expected: FAIL — `Call to undefined method App\Recap\RecapExporter::toPdf()`.

- [ ] **Step 3: Tulis `toPdf()`, view PDF, tombol, dan action**

`app/Recap/RecapExporter.php` — tambahkan `use Barryvdh\DomPDF\Facade\Pdf;` dan `use Symfony\Component\HttpFoundation\StreamedResponse;` bila belum ada, lalu method ini:
```php
    public function toPdf(string $periode = ''): StreamedResponse
    {
        $nama = 'rekap-absensi-'.($periode !== '' ? $periode : now()->format('Y-m')).'.pdf';
        return response()->streamDownload(function () use ($periode): void {
            echo Pdf::loadHTML(view('recap.pdf', ['result' => $this->result, 'periode' => $periode])->render())->output();
        }, $nama, ['Content-Type' => 'application/pdf']);
    }
```
`streamDownload` dipakai, bukan `response()->download()`, supaya file tidak pernah ditulis ke `storage/app` — kuota disk di shared hosting sering mepet.

`app/Livewire/AdminAttendanceReport.php` — tambahkan dua method ini, dengan `hasilEkspor()` yang sudah ada dari Task 5:
```php
    public function exportPdf(): StreamedResponse
    {
        return (new RecapExporter($this->hasilEkspor()))->toPdf($this->slugPeriode());
    }
    private function slugPeriode(): string
    {
        return $this->periodType === 'daily' ? $this->date : $this->month;
    }
```
`resources/views/recap/pdf.blade.php` — HTML print dengan gaya inline, supaya dompdf tidak mencari aset eksternal:
```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Absensi — {{ $periode }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 4px; text-align: left; }
        th { background: #f4f4f5; }
    </style>
</head>
<body>
    <h1>Rekap Absensi — {{ $periode }}</h1>
    <p>Tahun ajaran: {{ $result->academicYear->name }}</p>
    @include('recap.partials.matrix', ['result' => $result])
    <p>Total hadir {{ $result->summary['hadir'] }}, izin {{ $result->summary['izin'] }}, sakit {{ $result->summary['sakit'] }}, alpa {{ $result->summary['alpa'] }}</p>
</body>
</html>
```
`resources/views/livewire/admin-attendance-report.blade.php` — tambahkan tombol di samping "Unduh Excel":
```blade
            <button type="button" wire:click="exportPdf" class="rounded-md border border-zinc-900 bg-white px-3 py-2 text-sm">Unduh PDF</button>
```
- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=RecapExporterPdfTest
```
**Expected: 3 tests pass**

## Task 7: Empty state di ketiga halaman

- Modify: `resources/views/livewire/homeroom-class-recap.blade.php`
- Modify: `resources/views/livewire/teacher-subject-recap.blade.php`
- Modify: `resources/views/livewire/admin-attendance-report.blade.php`
- Test: `tests/Feature/Livewire/RecapEmptyStateTest.php`

Halaman kosong tanpa penjelasan dibaca sebagai error. Tiap kondisi kosong punya kalimat sendiri supaya pengguna tahu harus menghubungi admin atau menunggu data, bukan mencoba refresh halaman.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Livewire/RecapEmptyStateTest.php`:
```php
<?php
namespace Tests\Feature\Livewire;
use App\Livewire\{AdminAttendanceReport, HomeroomClassRecap, TeacherSubjectRecap};
use App\Models\{AcademicYear, Attendance, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class RecapEmptyStateTest extends TestCase
{
    use RefreshDatabase;
    public function test_tahun_ajaran_kosong_di_kedua_halaman(): void
    {
        RecapScenario::waliKelas();
        AcademicYear::query()->delete();
        Livewire::actingAs(User::factory()->guru()->create())->test(HomeroomClassRecap::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.')
            ->assertDontSee('Tidak ada data absensi pada periode ini.');
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Belum ada tahun ajaran. Hubungi admin.');
    }
    public function test_wali_belum_diwalikan_kelas(): void
    {
        RecapScenario::waliKelas();
        $guru = User::factory()->guru()->create();
        Livewire::actingAs($guru)->test(HomeroomClassRecap::class)
            ->assertSee('Kamu belum diwalikan kelas. Hubungi admin.');
    }
    public function test_guru_belum_punya_jadwal(): void
    {
        RecapScenario::waliKelas();
        Schedule::query()->delete();
        $guru = User::factory()->guru()->create();
        Livewire::actingAs($guru)->test(TeacherSubjectRecap::class)
            ->assertSee('Belum ada jadwal mengajar pada tahun ajaran ini.');
    }
    public function test_admin_belum_ada_kelas(): void
    {
        RecapScenario::waliKelas();
        Schedule::query()->delete();
        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSee('Tidak ada kelas pada tahun ajaran ini.');
    }
    public function test_kelas_ada_tapi_absensi_kosong(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Tidak ada data absensi pada periode ini.');
    }
    public function test_matriks_kosong_menampilkan_empat_kolom_ringkasan(): void
    {
        $s = RecapScenario::waliKelas();
        Attendance::query()->delete();
        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Hadir 0')->assertSee('Izin 0')
            ->assertSee('Sakit 0')->assertSee('Alpa 0');
    }
}
```
- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=RecapEmptyStateTest
```
Expected: FAIL — test tahun ajaran kosong dan kelas tapi absensi kosong gagal karena kondisi kosong belum dibedakan dari tabel kosong.

- [ ] **Step 3: Bedakan kondisi kosong di tiap view**

`resources/views/livewire/homeroom-class-recap.blade.php` — ganti cabang `@elseif ($daftarKelas->isEmpty())` menjadi:
```blade
        @elseif ($daftarKelas->isEmpty())
            <p class="text-sm text-zinc-600">Kamu belum diwalikan kelas. Hubungi admin.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600">Tidak ada siswa terdaftar di kelas ini.</p>
```
`resources/views/livewire/teacher-subject-recap.blade.php` — ganti cabang `@elseif ($result->classes->isEmpty())` menjadi:
```blade
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600">Belum ada jadwal mengajar pada tahun ajaran ini.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600">Kelas ada, tetapi belum ada siswa terdaftar.</p>
```
`resources/views/livewire/admin-attendance-report.blade.php` — ganti cabang `@elseif ($result->rows->isEmpty())` menjadi:
```blade
        @elseif ($result->classes->isEmpty())
            <p class="text-sm text-zinc-600">Tidak ada kelas pada tahun ajaran ini.</p>
        @elseif ($result->rows->isEmpty())
            <p class="text-sm text-zinc-600">Kelas ada, tetapi belum ada siswa terdaftar.</p>
```
Baris ringkasan di partial matriks sengaja tidak disembunyikan saat tabel kosong: pembaca tetap melihat angka nol yang sahih, bukan tabel yang hilang begitu saja.

- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=RecapEmptyStateTest
```
**Expected: 6 tests pass**

## Task 8: Konfigurasi untuk shared hosting

- Create: `config/rekap.php`
- Modify: `app/Recap/RecapExporter.php` (nama file dari konfigurasi)
- Modify: `phpunit.xml` (`APP_TIMEZONE` untuk test)
- Modify: `.env.example` (`APP_TIMEZONE`, `QUEUE_CONNECTION`)
- Test: `tests/Feature/Recap/RecapConfigurationTest.php`

Dua hal di sini merusak laporan tanpa error kalau dibiarkan: zona waktu yang salah menggeser rekap harian, dan nama file export yang tidak bisa diganti perinstansi. Keduanya karena itu dikunci test, bukan hanya ditulis di `.env.example`.

- [ ] **Step 1: Tulis test yang gagal**

`tests/Feature/Recap/RecapConfigurationTest.php`:
```php
<?php
namespace Tests\Feature\Recap;
use App\Models\User;
use App\Recap\{RecapExporter, RecapPeriod, RecapQueries\AdminRecapQuery};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RecapScenario;
use Tests\TestCase;
class RecapConfigurationTest extends TestCase
{
    use RefreshDatabase;
    public function test_nama_file_export_mengikuti_konfigurasi(): void
    {
        $s = RecapScenario::waliKelas();
        config(['rekap.file_prefix' => 'laporan-absensi']);
        $hasil = app(AdminRecapQuery::class)->for(User::factory()->admin()->create(), $s->tahun)->run(RecapPeriod::monthly(2025, 9));
        $this->assertSame('laporan-absensi-2025-09.xlsx', (new RecapExporter($hasil))->namaBerkas('2025-09', 'xlsx'));
        $this->assertSame('laporan-absensi-2025-09.pdf', (new RecapExporter($hasil))->namaBerkas('2025-09', 'pdf'));
    }
    public function test_zona_waktu_aplikasi_adalah_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('2025-09-15', now()->setTestNow('2025-09-15 23:30')->format('Y-m-d'));
    }
}
```
Test kedua memeriksa `config('app.timezone')`, bukan hanya hasil akhirnya: kalau test berjalan di UTC sementara produksi di Jakarta, tanggal absensi bisa berbeda satu hari dan semua rekap harian masuk kolom yang salah tanpa error.

- [ ] **Step 2: Jalankan test, pastikan gagal**
```powershell
php artisan test --filter=RecapConfigurationTest
```
Expected: FAIL — `Call to undefined method App\Recap\RecapExporter::namaBerkas()` dan `config('rekap.file_prefix')` belum ada.

- [ ] **Step 3: Tulis config dan pakai di exporter**

`config/rekap.php`:
```php
<?php
return [
    'file_prefix' => env('REKAP_FILE_PREFIX', 'rekap-absensi'),
];
```
`.env.example` — tambahkan dua baris ini kalau belum ada:
```
APP_TIMEZONE=Asia/Jakarta
QUEUE_CONNECTION=sync
```
`phpunit.xml` — tambahkan satu entri `<env>` di dalam blok `<php>` supaya test memakai zona waktu yang sama dengan produksi:
```xml
        <env name="APP_TIMEZONE" value="Asia/Jakarta"/>
```
`app/Recap/RecapExporter.php` — tambahkan method ini:
```php
    public function namaBerkas(string $slug, string $ekstensi = 'xlsx'): string
    {
        return config('rekap.file_prefix').'-'.($slug !== '' ? $slug : now()->format('Y-m')).'.'.$ekstensi;
    }
```
Di `toPdf()` Task 6, ganti baris nama file dengan:
```php
        $nama = $this->namaBerkas($periode, 'pdf');
```
- [ ] **Step 4: Jalankan test, pastikan lulus**
```powershell
php artisan test --filter=RecapConfigurationTest
```
**Expected: 2 tests pass**

## Task 9: SOP deploy, backup, dan pemulihan

- Create: `docs/SOP-DEPLOY.md`

Task ini tidak menulis test: yang diuji adalah dokumen, dan dokumen tidak punya runner. Isi file ini adalah hasil akhir dan harus ditulis persis seperti di bawah ini.

- [ ] **Step 1: Tulis `docs/SOP-DEPLOY.md`**
```markdown
# SOP Deploy Sistem Absensi

## Prasyarat
Akses cPanel dan phpMyAdmin, MySQL 8, serta PHP sesuai versi yang dipakai Plan 1.

## Deploy pertama
1. Unggah kode ke `public_html/absensi`, dengan folder `public` Laravel menjadi document root.
2. Buat database di cPanel, lalu isi `.env`: `DB_*`, `APP_KEY` dari instalasi lama bila ada, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_TIMEZONE=Asia/Jakarta`, `QUEUE_CONNECTION=sync`.
3. `php artisan key:generate` hanya kalau `APP_KEY` belum pernah dibuat. Mengganti `APP_KEY` membatalkan seluruh cookie sesi lama.
4. `php artisan migrate --force`
5. `php artisan db:seed --force` hanya untuk mengisi admin pertama dan tahun ajaran awal.
6. `php artisan storage:link`. Kalau hosting memblokir symlink, jalankan `php artisan storage:unlink`, salin `storage/app/public` ke `public/storage` lewat File Manager, dan pastikan `public/storage` writable.
7. Buka `/up` untuk memastikan aplikasi sehat sebelum halaman lama diganti.

## Deploy berikutnya
1. Backup database lewat phpMyAdmin: export semua tabel sebagai `.sql`.
2. Unggah kode baru, pertahankan file `.env` yang sudah ada.
3. `php artisan migrate --force`, lalu `php artisan optimize:clear`.
4. Buka `/up`, cek login, dan cocokkan satu rekap bulanan.

## Backup rutin
Database di-export mingguan dan disimpan minimal 4 minggu di luar server. Kode tidak perlu dicadangkan karena sumbernya ada di repository.

## Pemulihan
1. Pecahkan serialize `.sql` ke database baru.
2. Salin `.env` lama ke folder kode baru.
3. `php artisan migrate:status` untuk memastikan tidak ada migrasi yang tertinggal.
4. Jalankan `php artisan storage:link` atau langkah 6 di atas.
5. Login sebagai admin dan cocokkan satu rekap bulanan dengan hasil export minggu sebelumnya.

## Yang tidak boleh dilakukan
- `php artisan migrate:fresh` di production. Itu menghapus seluruh data absensi.
- Mengganti `APP_KEY` tanpa alasan. Semua sesi keluar dan cookie lama tidak berlaku.
- Menjalankan scheduler atau `queue:work`. `QUEUE_CONNECTION=sync` dan aplikasi tidak punya scheduled task.
```
- [ ] **Step 2: Verifikasi dokumen**

Baca ulang `docs/SOP-DEPLOY.md` dan pastikan setiap perintah yang disebut benar-benar ada di plan ini atau plan sebelumnya. Larangan `migrate:fresh` dan penggantian `APP_KEY` harus tertulis, karena keduanya menghapus atau mengunci data secara permanen.

## Task 10: Penutupan plan dan verifikasi akhir

- Verify: seluruh file yang disebut di bagian `File Structure`

- [ ] **Step 1: Jalankan seluruh test suite**
```powershell
php artisan test
```
Expected: 34 tests pass.

- [ ] **Step 2: Jalankan pemeriksaan rute**
```powershell
php artisan route:list --path=rekap
```
Expected: `/rekap`, `/rekap/mapel-saya`, dan `/rekap/admin` ada dan memakai middleware `auth`.

- [ ] **Step 3: Checklist akhir**

- [ ] Semua 34 test hijau pada `php artisan test`.
- [ ] `/up` merespons 200 di production dengan `APP_DEBUG=false`.
- [ ] Login dengan akun wali kelas, guru mapel, dan admin; masing-masing hanya melihat tabnya.
- [ ] Rekap bulanan, harian, dan drill-down menghasilkan angka yang sama dengan file Excel dan PDF untuk periode yang sama.
- [ ] `docs/SOP-DEPLOY.md` sudah dibaca sekali oleh operator.

---

## Verifikasi akhir plan ini

Jalankan dari folder `docs/specs/plans`:
```powershell
$p = '2026-09-26-plan-5-rekap-dan-deploy.md'
$baris = Get-Content -LiteralPath $p
$baris.Count
(Select-String -LiteralPath $p -Pattern ('function ' + 'test_')).Count
(Select-String -LiteralPath $p -Pattern 'Expected: \d+ tests pass').Count
$forbidden = ('TO' + 'DO') + '|' + ('LAN' + 'JUTAN') + '|perbaiki ' + 'nanti|' + ('Gan' + 'ti (blok|kedua)') + '|versi di ' + 'atas|\.\.\.' + 'existing'
(Select-String -LiteralPath $p -Pattern $forbidden).Count
(Select-String -LiteralPath $p -Pattern '[\u4e00-\u9fff\u3040-\u30ff\uac00-\ud7af]').Count
```
Pola dipecah dengan penggabungan string supaya perintah ini tidak mencocoki dirinya sendiri. Yang harus benar: jumlah baris di bawah 1500, 34 test method, 9 blok `Expected: N tests pass` (delapan task ber-test ditambah assertion suite penuh di Task 10), dan dua hitungan terakhir bernilai 0.

---

## Di luar lingkup plan ini

- Login orang tua, notifikasi WhatsApp, dan QR atau face recognition (spes §10). Tidak ada di v1.
- Scheduled task apa pun: `QUEUE_CONNECTION=sync` dan shared hosting tidak punya cron yang bisa diandalkan.
- Menulis data dari halaman rekap. Ketiga halaman hanya membaca `attendances`; koreksi absensi tetap lewat `AttendanceRecorder`.
- Ekspor per kelas untuk wali kelas. Ekspor hanya milik admin di Task 5 dan 6.
