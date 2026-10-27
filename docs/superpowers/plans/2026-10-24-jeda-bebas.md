# Jeda Jadwal Bebas per Hari — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Jeda (Upacara/Istirahat) bisa ditambah, diubah, dan dihapus admin untuk tiap hari Senin–Jumat.

**Architecture:** Tabel `school_breaks` menggantikan daftar jeda di kode. `SchoolBreaks` membaca database dan tetap dipakai validasi jadwal + grid. CRUD lewat Livewire page `/master/jeda` dengan `SchoolBreakManager` sebagai tempat validasi bentrok.

**Tech Stack:** Laravel 13, Livewire 4, PHPUnit, MySQL.

**Spec:** `docs/superpowers/specs/2026-10-24-jeda-bebas-design.md`

## Global Constraints

- Hanya admin yang boleh membuka dan mengubah jeda; guru ditolak 403.
- Data awal migrasi harus persis: Senin `07:00–07:45 Upacara` (after 0), `10:05–10:35 Istirahat` (after 4), `12:20–12:50 Istirahat / Sholat` (after 7).
- Nama jeda wajib, maksimal 50 karakter; `after` bilangan bulat 0–20; jam mulai < jam selesai.
- Jeda yang bentrok dengan jeda lain atau dengan jadwal aktif di hari yang sama ditolak.
- Zona waktu dan format jam mengikuti `Schedule`: `time` di database, tampil `H:i`.
- Bahasa UI dan pesan error: Indonesia.

## Review Focus

- Jeda ditambahkan pada hari tanpa jadwal sama sekali: baris tetap muncul di grid pada posisi `after` yang benar.
- Jeda diedit sehingga menabrak jadwal lama: harus ditolak, bukan menyimpan data yang membuat validasi jadwal lama tidak berlaku.
- Jeda dihapus: slot jam itu harus langsung bisa dipakai lagi saat menyimpan jadwal.
- Dua hari punya jeda di posisi yang sama: keduanya tampil dalam satu baris, bukan saling menimpa.
- Admin membuka `/master/jeda` lewat URL langsung: tetap tampil; guru lewat URL langsung: 403.

---

### Task 1: Tabel, model, dan SchoolBreaks dari database

**Files:**
- Create: `database/migrations/2026_10_24_000001_create_school_breaks_table.php`
- Create: `app/Models/SchoolBreak.php`
- Create: `database/factories/SchoolBreakFactory.php`
- Modify: `app/Support/SchoolBreaks.php`
- Test: `tests/Feature/SchoolBreaksTest.php`

**Interfaces:**
- Consumes: `App\Enums\DayOfWeek`.
- Produces:
  - `SchoolBreak` fillable: `day_of_week`, `label`, `start_time`, `end_time`, `after`; cast `day_of_week` ke `DayOfWeek`, `start_time`/`end_time` ke `datetime:H:i`, `after` ke `integer`.
  - `SchoolBreaks::forDay(DayOfWeek $day): array` → `list<array{after:int,label:string,start:string,end:string}>` urut `after`, `start_time`.
  - `SchoolBreaks::overlap(DayOfWeek $day, string $startTime, string $endTime): ?array` (bentuk array sama seperti elemen `forDay`).
  - `SchoolBreaks::periodFor(...)` tidak berubah.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SchoolBreaksTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Support\SchoolBreaks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolBreaksTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_awal_jeda_senin_tersedia(): void
    {
        $this->assertSame([
            ['after' => 0, 'label' => 'Upacara', 'start' => '07:00', 'end' => '07:45'],
            ['after' => 4, 'label' => 'Istirahat', 'start' => '10:05', 'end' => '10:35'],
            ['after' => 7, 'label' => 'Istirahat / Sholat', 'start' => '12:20', 'end' => '12:50'],
        ], SchoolBreaks::forDay(DayOfWeek::Senin));
    }

    public function test_hari_lain_tanpa_jeda_mengembalikan_array_kosong(): void
    {
        $this->assertSame([], SchoolBreaks::forDay(DayOfWeek::Selasa));
    }

    public function test_overlap_memakai_jeda_dari_database(): void
    {
        $this->assertSame('Istirahat', SchoolBreaks::overlap(DayOfWeek::Senin, '10:15', '10:45')['label']);
        $this->assertNull(SchoolBreaks::overlap(DayOfWeek::Senin, '10:35', '11:10'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SchoolBreaksTest`
Expected: FAIL — `school_breaks` table tidak ada.

- [ ] **Step 3: Create migration with default rows**

`database/migrations/2026_10_24_000001_create_school_breaks_table.php`:

```php
Schema::create('school_breaks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->tinyInteger('day_of_week');
    $table->string('label', 50);
    $table->time('start_time');
    $table->time('end_time');
    $table->tinyInteger('after');
    $table->timestamps();
    $table->index(['day_of_week', 'after']);
});
```

After `Schema::create`, insert the three Monday rows from Global Constraints with `Str::uuid()` and `now()` for timestamps, inside the same migration.

- [ ] **Step 4: Create model and factory**

`app/Models/SchoolBreak.php` uses `HasFactory`, `HasUuids`, `$fillable` and `casts()` per the Interfaces block. `database/factories/SchoolBreakFactory.php` default: `day_of_week => DayOfWeek::Senin`, `label => 'Istirahat'`, `start_time => '10:05:00'`, `end_time => '10:35:00'`, `after => 4`.

- [ ] **Step 5: Rewrite `SchoolBreaks::forDay` to query the table**

```php
public static function forDay(DayOfWeek $day): array
{
    return SchoolBreak::query()
        ->where('day_of_week', $day->value)
        ->orderBy('after')
        ->orderBy('start_time')
        ->get()
        ->map(fn (SchoolBreak $break) => [
            'after' => $break->after,
            'label' => $break->label,
            'start' => $break->start_time->format('H:i'),
            'end' => $break->end_time->format('H:i'),
        ])
        ->all();
}
```

`overlap` and `periodFor` stay as-is.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SchoolBreaksTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/SchoolBreak.php app/Support/SchoolBreaks.php database/migrations database/factories/SchoolBreakFactory.php tests/Feature/SchoolBreaksTest.php
git commit -m "feat: simpan jeda jadwal di database"
```

---

### Task 2: Grid menampilkan jeda semua hari

**Files:**
- Modify: `app/Livewire/ScheduleManagement.php:317-359`
- Modify: `resources/views/livewire/schedule-management.blade.php:87-101`
- Test: `tests/Feature/Livewire/ScheduleManagementTest.php` (ubah 3 assertion lama, tambah 2 tes)

**Interfaces:**
- Consumes: `SchoolBreaks::forDay()` dari Task 1.
- Produces: slot grid `['type' => 'break', 'jeda' => Collection<int, array{after:int,label:string,start:string,end:string,day:DayOfWeek}>]` dan slot pelajaran yang tidak berubah.

- [ ] **Step 1: Update failing assertions and add tests**

Di tiga tes lama, ganti akses `$grid[n]['jeda']['label']` / `['after']` menjadi `$grid[n]['jeda']->first()['label']` / `['after']` (baris 420, 478, 522, 558).

Tambah dua tes:

```php
public function test_jeda_dari_hari_berbeda_tampil_dalam_satu_baris(): void
{
    $admin = User::factory()->admin()->create();
    SchoolBreak::factory()->create([
        'day_of_week' => DayOfWeek::Selasa,
        'label' => 'Istirahat Selasa',
        'start_time' => '10:05',
        'end_time' => '10:15',
        'after' => 4,
    ]);

    $grid = Livewire::actingAs($admin)->test(ScheduleManagement::class)->viewData('grid');

    $barisJeda = $grid->where('type', 'break')
        ->first(fn ($slot) => $slot['jeda']->first()['after'] === 4);
    $this->assertSame(
        [DayOfWeek::Senin, DayOfWeek::Selasa],
        $barisJeda['jeda']->pluck('day')->all(),
    );
}

public function test_hari_tanpa_jeda_tidak_ikut_di_baris_jeda(): void
{
    $admin = User::factory()->admin()->create();
    SchoolBreak::factory()->create([
        'day_of_week' => DayOfWeek::Selasa,
        'start_time' => '09:00',
        'end_time' => '09:15',
        'after' => 2,
    ]);

    $grid = Livewire::actingAs($admin)->test(ScheduleManagement::class)->viewData('grid');
    $baris = $grid->where('type', 'break')
        ->first(fn ($slot) => $slot['jeda']->first()['after'] === 2);

    $this->assertSame([DayOfWeek::Selasa], $baris['jeda']->pluck('day')->all());
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ScheduleManagementTest`
Expected: FAIL — bentuk `jeda` masih array tunggal.

- [ ] **Step 3: Rewrite the grid query in `ScheduleManagement::render`**

Replace `$jedaSenin`, `$adaSenin`, and `$grid` with:

```php
$jedaPerPosisi = collect(DayOfWeek::cases())
    ->flatMap(fn (DayOfWeek $hari) => collect(SchoolBreaks::forDay($hari))
        ->map(fn (array $jeda) => [...$jeda, 'day' => $hari]))
    ->groupBy('after');

$jumlahBaris = max(
    $perHari->max(fn ($baris) => $baris->keys()->max() ?? 0) ?? 0,
    $jedaPerPosisi->keys()->max() ?? 0,
);

$slotJeda = fn (int $nomor) => ['type' => 'break', 'jeda' => $jedaPerPosisi->get($nomor, collect())];

$grid = $jumlahBaris > 0
    ? collect(range(1, $jumlahBaris))
        ->flatMap(function (int $nomor) use ($perHari, $jedaPerPosisi, $slotJeda) {
            $baris = [[
                'type' => 'lesson',
                'nomor' => $nomor,
                'hari' => $perHari->map(fn ($baris) => $baris->get($nomor, collect())),
            ]];

            if ($jedaPerPosisi->has($nomor)) {
                $baris[] = $slotJeda($nomor);
            }

            return $baris;
        })
        ->when($jedaPerPosisi->has(0), fn ($grid) => $grid->prepend($slotJeda(0)))
    : collect();
```

- [ ] **Step 4: Update the break row in the Blade view**

Ganti isi `<tr>` jeda dengan satu baris berisi daftar semua jeda di posisi itu:

```blade
<th class="...">{{ $slot['jeda']->contains(fn ($jeda) => $jeda['after'] === 0) ? 'Upacara' : 'Jeda' }}</th>
<td colspan="{{ count($hari) }}" class="...">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs ...">
        @foreach ($slot['jeda'] as $jeda)
            <span class="inline-flex flex-wrap items-center gap-x-2">
                <span class="rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-100">{{ $jeda['day']->label() }}</span>
                <span class="font-semibold ...">{{ $jeda['label'] }}</span>
                <span>·</span>
                <span>{{ str_replace(':', '.', $jeda['start']) }}–{{ str_replace(':', '.', $jeda['end']) }}</span>
            </span>
        @endforeach
    </div>
</td>
```

Pertahankan kelas amber dan `border-y-2` yang sudah ada.

- [ ] **Step 5: Run the test file**

Run: `php artisan test --filter=ScheduleManagementTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/ScheduleManagement.php resources/views/livewire/schedule-management.blade.php tests/Feature/Livewire/ScheduleManagementTest.php
git commit -m "feat: tampilkan jeda semua hari di grid jadwal"
```

---

### Task 3: Validasi bentrok saat menyimpan jeda

**Files:**
- Create: `app/Services/SchoolBreakManager.php`
- Test: `tests/Feature/SchoolBreakManagerTest.php`

**Interfaces:**
- Consumes: `SchoolBreak` (Task 1), `Schedule`.
- Produces:
  - `SchoolBreakManager::save(?SchoolBreak $break, array $data): SchoolBreak` dengan `$data = ['day_of_week' => string, 'label' => string, 'start_time' => string, 'end_time' => string, 'after' => int|string]`.
  - `SchoolBreakManager::hapus(SchoolBreak $break): void` (hapus permanen).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\SchoolBreak;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\SchoolBreakManager;
use App\Services\ScheduleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolBreakManagerTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $ubah = []): array
    {
        return [
            'day_of_week' => '2',
            'label' => 'Istirahat',
            'start_time' => '10:00',
            'end_time' => '10:15',
            'after' => 4,
            ...$ubah,
        ];
    }

    public function test_jeda_bentrok_jadwal_ditolak(): void
    {
        Schedule::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:10',
            'end_time' => '10:40',
        ]);

        try {
            app(SchoolBreakManager::class)->save(null, $this->data());
            $this->fail('Jeda seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('dipakai jadwal', $e->errors()['start_time'][0]);
        }

        $this->assertSame(0, SchoolBreak::where('day_of_week', DayOfWeek::Selasa->value)->count());
    }

    public function test_jeda_bentrok_jeda_lain_ditolak(): void
    {
        SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:10',
            'end_time' => '10:30',
            'after' => 4,
        ]);

        $this->expectException(ValidationException::class);

        app(SchoolBreakManager::class)->save(null, $this->data());
    }

    public function test_jeda_tepat_di_batas_jadwal_diterima(): void
    {
        Schedule::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:15',
            'end_time' => '10:45',
        ]);

        app(SchoolBreakManager::class)->save(null, $this->data());

        $this->assertSame(1, SchoolBreak::where('label', 'Istirahat')->count());
    }

    public function test_jeda_yang_diubah_tidak_bentrok_dengan_dirinya_sendiri(): void
    {
        $jeda = SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:00',
            'end_time' => '10:15',
        ]);

        app(SchoolBreakManager::class)->save($jeda, $this->data([
            'label' => 'Istirahat Baru',
        ]));

        $this->assertSame('Istirahat Baru', $jeda->fresh()->label);
    }

    public function test_hapus_membebaskan_slot_jam(): void
    {
        $jeda = SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:00',
            'end_time' => '10:15',
        ]);

        app(SchoolBreakManager::class)->hapus($jeda);

        $this->assertDatabaseMissing('school_breaks', ['id' => $jeda->id]);

        app(ScheduleManager::class)->save(null, [
            'class_id' => SchoolClass::factory()->create()->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => User::factory()->guru()->create()->id,
            'day_of_week' => '2',
            'start_time' => '10:00',
            'end_time' => '10:15',
        ]);

        $this->assertSame(1, Schedule::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SchoolBreakManagerTest`
Expected: FAIL — class `SchoolBreakManager` tidak ada.

- [ ] **Step 3: Implement `SchoolBreakManager`**

`save` harus, berurutan:
1. Bentrok jeda lain: query `school_breaks` dengan `day_of_week` sama, `id != $break?->id`, `start_time < $data['end_time']` dan `end_time > $data['start_time']`. Jika ada, `ValidationException::withMessages(['start_time' => "Jam {$mulai}–{$selesai} sudah dipakai jeda {$label}."])`.
2. Bentrok jadwal aktif: query `Schedule` dengan `day_of_week` sama dan irisan waktu yang sama; `with(['schoolClass', 'subject'])`. Jika ada, pesan: `"Jam ini dipakai jadwal {$kelas} {$mapel} ({$mulai}–{$selesai})."` pada field `start_time`.
3. `fill` + `save`, kembalikan model.

`hapus` cukup `$break->delete()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SchoolBreakManagerTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SchoolBreakManager.php tests/Feature/SchoolBreakManagerTest.php
git commit -m "feat: validasi bentrok jeda jadwal"
```

---

### Task 4: Halaman Atur Jeda

**Files:**
- Create: `app/Policies/SchoolBreakPolicy.php`
- Create: `app/Livewire/SchoolBreakManagement.php`
- Create: `resources/views/livewire/school-break-management.blade.php`
- Modify: `routes/master.php`
- Modify: `resources/views/components/nav/data-master.blade.php`
- Modify: `resources/views/livewire/schedule-management.blade.php` (tombol pintasan)
- Test: `tests/Feature/Livewire/SchoolBreakManagementTest.php`, `tests/Feature/Livewire/SidebarTest.php`

**Interfaces:**
- Consumes: `SchoolBreak`, `SchoolBreakManager::save()/hapus()`.
- Produces: route `master.jeda`, komponen Livewire `App\Livewire\SchoolBreakManagement` dengan props `editingId`, `day_of_week`, `label`, `start_time`, `end_time`, `after` dan method `newForm`, `editForm`, `save`, `hapus`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Livewire/SchoolBreakManagementTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Enums\DayOfWeek;
use App\Livewire\SchoolBreakManagement;
use App\Models\SchoolBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolBreakManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_jeda_hari_lain(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->set('day_of_week', (string) DayOfWeek::Rabu->value)
            ->set('label', 'Istirahat')
            ->set('start_time', '09:30')
            ->set('end_time', '09:45')
            ->set('after', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_breaks', ['day_of_week' => 3, 'label' => 'Istirahat']);
    }

    public function test_admin_bisa_mengubah_dan_menghapus_jeda(): void
    {
        $admin = User::factory()->admin()->create();
        $jeda = SchoolBreak::factory()->create(['label' => 'Lama']);

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->call('editForm', $jeda->id)
            ->assertSet('label', 'Lama')
            ->set('label', 'Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_breaks', ['id' => $jeda->id, 'label' => 'Baru']);

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->call('hapus', $jeda->id);

        $this->assertDatabaseMissing('school_breaks', ['id' => $jeda->id]);
    }

    public function test_jam_mulai_harus_sebelum_jam_selesai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SchoolBreakManagement::class)
            ->set('day_of_week', '2')
            ->set('label', 'Istirahat')
            ->set('start_time', '10:00')
            ->set('end_time', '09:00')
            ->set('after', 4)
            ->call('save')
            ->assertHasErrors(['end_time']);
    }

    public function test_guru_tidak_bisa_membuka_halaman_jeda(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get('/master/jeda')->assertForbidden();
    }
}
```

Tambahkan di `SidebarTest`: admin melihat `route('master.jeda')` dan teks `Jeda`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="SchoolBreakManagementTest|SidebarTest"`
Expected: FAIL — route/komponen belum ada.

- [ ] **Step 3: Create policy, route, nav, and shortcut**

- `SchoolBreakPolicy`: `viewAny`, `create`, `update`, `delete` semuanya `$user->isAdmin()`.
- `routes/master.php`: `Route::get('jeda', SchoolBreakManagement::class)->name('jeda');`
- `data-master.blade.php`: `NavLink::adminOnly('Jeda', 'master.jeda', 'pause')` setelah Jadwal.
- Di header grid `schedule-management.blade.php`, tambahkan link `Atur Jeda` ke `route('master.jeda')` dengan `wire:navigate`, memakai gaya tombol outline yang sudah dipakai halaman itu.

- [ ] **Step 4: Implement the Livewire component**

Rules:

```php
'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
'label' => ['required', 'string', 'max:50'],
'start_time' => ['required', 'date_format:H:i'],
'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
'after' => ['required', 'integer', 'min:0', 'max:20'],
```

- `mount()`: `Gate::authorize('viewAny', SchoolBreak::class)`.
- `newForm()`: reset ke `day_of_week = '1'`, field lain kosong, `after = 0`.
- `editForm(string $id)`: ambil model, authorize `update`, isi field (`start_time`/`end_time` pakai `->format('H:i')`).
- `save()`: `validate()`, authorize create/update, panggil manager, `newForm()`, flash `Jeda ditambahkan.` / `Jeda diperbarui.`.
- `hapus(string $id)`: authorize delete, manager `hapus`, flash `Jeda dihapus.`.
- `render()`: authorize `viewAny`, kirim `daftar` = semua jeda urut `day_of_week`, `after`, `start_time`, dan `hari` = `DayOfWeek::cases()`; `->title('Atur Jeda')`.

- [ ] **Step 5: Create the Blade view**

Susun seperti `subject-management.blade.php`: judul, pesan sukses, form kiri (Hari select, Nama, Mulai, Selesai, Setelah jam ke- number 0–20), tabel kanan berisi Hari, Nama, Jam, Setelah jam, Aksi (Ubah/Hapus). Tombol Hapus pakai `wire:confirm="Hapus jeda ini?"`. Tampilkan jam sebagai `H:i`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter="SchoolBreakManagementTest|SidebarTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Policies/SchoolBreakPolicy.php app/Livewire/SchoolBreakManagement.php resources/views/livewire/school-break-management.blade.php routes/master.php resources/views/components/nav/data-master.blade.php resources/views/livewire/schedule-management.blade.php tests/Feature/Livewire/SchoolBreakManagementTest.php tests/Feature/Livewire/SidebarTest.php
git commit -m "feat: halaman atur jeda jadwal"
```

---

### Task 5: Verifikasi menyeluruh

**Files:** tidak ada file baru.

- [ ] **Step 1: Jalankan seluruh test**

Run: `php artisan test`
Expected: semua lulus, termasuk `ScheduleBreakTest` lama yang kini memakai data migrasi.

- [ ] **Step 2: Rapikan format**

Run: `vendor/bin/pint --dirty`
Expected: tidak ada error.

- [ ] **Step 3: Cek manual singkat**

Jalankan `php artisan serve`, buka `/master/jeda`, tambah jeda Rabu setelah jam 4, buka `/master/jadwal` dan pilih kelas: baris jeda Rabu muncul di baris setelah jam 4. Hapus jeda itu dan pastikan barisnya hilang.

- [ ] **Step 4: Commit sisa perubahan**

```bash
git add -A
git commit -m "chore: rapikan format jeda jadwal"
```
