# Istirahat Terkunci pada Jadwal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menampilkan dua jeda istirahat Senin di grid jadwal dan menolak jadwal Senin yang menabrak jeda tersebut.

**Architecture:** Aturan jeda disimpan di satu support class `SchoolBreaks`. `ScheduleManager` memakainya untuk validasi, sedangkan `ScheduleManagement` memakainya untuk menyisipkan baris jeda dan memetakan jam Senin ke nomor jam.

**Tech Stack:** Laravel, Livewire, PHPUnit, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-10-22-istirahat-jadwal-design.md`

## Global Constraints

- Jeda hanya berlaku untuk **Senin**.
- Jeda pertama: `10:05–10:35`, label `Istirahat`, setelah jam 4.
- Jeda kedua: `12:20–12:50`, label `Istirahat / Sholat`, setelah jam 7.
- Tidak menambah tabel, model, atau master data baru.
- Jadwal Selasa–Jumat dan jadwal lama tidak boleh berubah.
- Error validasi ditempel pada field `start_time`.

## Review Focus

- Jadwal Senin yang berakhir tepat `10:05` harus tetap diterima.
- Jadwal Senin yang mulai tepat `10:35` harus tetap diterima.
- Jadwal Selasa pada jam yang sama harus tetap diterima.
- Kelas dengan jam Senin bolong, seperti 7-A, harus menaruh jeda setelah jam 4 dan jam 7, bukan setelah pelajaran terakhir.
- Test grid lama yang memakai jam di luar struktur Senin harus tetap lulus.

---

### Task 1: Kunci jeda di `ScheduleManager`

**Files:**
- Create: `app/Support/SchoolBreaks.php`
- Modify: `app/Services/ScheduleManager.php`
- Test: `tests/Feature/ScheduleBreakTest.php`

**Interfaces:**
- Consumes: `App\Enums\DayOfWeek`
- Produces:
  - `SchoolBreaks::forDay(DayOfWeek $day): array` → daftar `['after' => int, 'label' => string, 'start' => string, 'end' => string]`
  - `SchoolBreaks::periodFor(DayOfWeek $day, string $startTime): ?int`
  - `SchoolBreaks::overlap(DayOfWeek $day, string $startTime, string $endTime): ?array`

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/ScheduleBreakTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScheduleBreakTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $day, string $start, string $end): array
    {
        return [
            'class_id' => SchoolClass::factory()->create()->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => User::factory()->guru()->create()->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    public function test_jadwal_senin_yang_menabrak_istirahat_pertama_ditolak(): void
    {
        try {
            app(ScheduleManager::class)->save(null, $this->data('1', '10:15', '10:45'));

            $this->fail('Jadwal seharusnya ditolak karena menabrak istirahat.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Jam 10:05–10:35 dipakai untuk Istirahat.',
                $e->errors()['start_time'][0],
            );
        }

        $this->assertSame(0, Schedule::count());
    }

    public function test_jadwal_senin_yang_menabrak_istirahat_kedua_ditolak(): void
    {
        try {
            app(ScheduleManager::class)->save(null, $this->data('1', '12:30', '13:00'));

            $this->fail('Jadwal seharusnya ditolak karena menabrak istirahat.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Jam 12:20–12:50 dipakai untuk Istirahat / Sholat.',
                $e->errors()['start_time'][0],
            );
        }

        $this->assertSame(0, Schedule::count());
    }

    public function test_jadwal_senin_tepat_di_batas_jeda_tetap_diterima(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('1', '09:30', '10:05'));
        app(ScheduleManager::class)->save(null, $this->data('1', '10:35', '11:10'));

        $this->assertSame(2, Schedule::count());
    }

    public function test_jadwal_selasa_pada_jam_yang_sama_tidak_terkunci(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('2', '10:15', '10:45'));

        $this->assertSame(1, Schedule::count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ScheduleBreakTest`

Expected: FAIL because `SchoolBreaks` does not exist and overlapping Monday schedules are still accepted.

- [ ] **Step 3: Create `app/Support/SchoolBreaks.php`**

```php
<?php

namespace App\Support;

use App\Enums\DayOfWeek;

final class SchoolBreaks
{
    /**
     * @return list<array{after: int, label: string, start: string, end: string}>
     */
    public static function forDay(DayOfWeek $day): array
    {
        return $day === DayOfWeek::Senin ? [
            ['after' => 4, 'label' => 'Istirahat', 'start' => '10:05', 'end' => '10:35'],
            ['after' => 7, 'label' => 'Istirahat / Sholat', 'start' => '12:20', 'end' => '12:50'],
        ] : [];
    }

    public static function periodFor(DayOfWeek $day, string $startTime): ?int
    {
        if ($day !== DayOfWeek::Senin) {
            return null;
        }

        return match (substr($startTime, 0, 5)) {
            '07:45' => 1,
            '08:20' => 2,
            '08:55' => 3,
            '09:30' => 4,
            '10:35' => 5,
            '11:10' => 6,
            '11:45' => 7,
            '12:50' => 8,
            '13:25' => 9,
            default => null,
        };
    }

    /**
     * @return array{after: int, label: string, start: string, end: string}|null
     */
    public static function overlap(DayOfWeek $day, string $startTime, string $endTime): ?array
    {
        $mulai = self::toSeconds($startTime);
        $selesai = self::toSeconds($endTime);

        foreach (self::forDay($day) as $jeda) {
            if ($mulai < self::toSeconds($jeda['end']) && self::toSeconds($jeda['start']) < $selesai) {
                return $jeda;
            }
        }

        return null;
    }

    private static function toSeconds(string $time): int
    {
        [$jam, $menit, $detik] = array_pad(explode(':', $time), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
}
```

- [ ] **Step 4: Wire the break check into `ScheduleManager::save`**

In `app/Services/ScheduleManager.php`, import `App\Support\SchoolBreaks`, then add this before `validasiBatasKelas`:

```php
$jeda = SchoolBreaks::overlap($hari, $data['start_time'], $data['end_time']);

if ($jeda !== null) {
    throw ValidationException::withMessages([
        'start_time' => "Jam {$jeda['start']}–{$jeda['end']} dipakai untuk {$jeda['label']}.",
    ]);
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ScheduleBreakTest`

Expected: PASS, 4 tests.

- [ ] **Step 6: Format and commit**

Run: `vendor\bin\pint app/Support/SchoolBreaks.php app/Services/ScheduleManager.php tests/Feature/ScheduleBreakTest.php`

```bash
git add app/Support/SchoolBreaks.php app/Services/ScheduleManager.php tests/Feature/ScheduleBreakTest.php docs/superpowers/specs/2026-10-22-istirahat-jadwal-design.md docs/superpowers/plans/2026-10-22-istirahat-jadwal.md
git commit -m "feat: lock Monday break slots"
```

---

### Task 2: Tampilkan baris jeda di grid

**Files:**
- Modify: `app/Livewire/ScheduleManagement.php`
- Modify: `resources/views/livewire/schedule-management.blade.php`
- Test: `tests/Feature/Livewire/ScheduleManagementTest.php`

**Interfaces:**
- Consumes: `SchoolBreaks::forDay()`, `SchoolBreaks::periodFor()`
- Produces: `grid` rows with `type` = `lesson` or `break`; break rows carry `jeda` with `label`, `start`, `end`, `after`.

- [ ] **Step 1: Write the failing Livewire test**

Add to `tests/Feature/Livewire/ScheduleManagementTest.php`:

```php
public function test_grid_menampilkan_jeda_istirahat_senin(): void
{
    $admin = User::factory()->admin()->create();
    $kelas = SchoolClass::factory()->create();
    $guru = User::factory()->guru()->create();
    $mapel = Subject::factory()->create();

    foreach ([
        ['09:30', '10:05'],
        ['10:35', '11:10'],
        ['11:45', '12:20'],
    ] as [$mulai, $selesai]) {
        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => $mulai,
            'end_time' => $selesai,
        ]);
    }

    Livewire::actingAs($admin)
        ->test(ScheduleManagement::class)
        ->assertSee('Istirahat')
        ->assertSee('10.05–10.35')
        ->assertSee('Istirahat / Sholat')
        ->assertSee('12.20–12.50');
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_grid_menampilkan_jeda_istirahat_senin`

Expected: FAIL because the grid does not render break rows.

- [ ] **Step 3: Build break rows in `ScheduleManagement::render`**

Replace the current `$perHari` / `$jumlahBaris` / `$grid` block with:

```php
$perHari = $daftar
    ->where('class_id', $kelasAktif?->id)
    ->groupBy(fn (Schedule $jadwal) => $jadwal->day_of_week->value);

$perHari = collect(DayOfWeek::cases())->mapWithKeys(function (DayOfWeek $hari) use ($perHari) {
    $baris = ($perHari->get($hari->value) ?? collect())
        ->sortBy('start_time')
        ->values();

    return [$hari->value => $baris->mapWithKeys(function (Schedule $jadwal, int $index) use ($hari) {
        $nomor = SchoolBreaks::periodFor($hari, $jadwal->start_time->format('H:i')) ?? $index + 1;

        return [$nomor => $jadwal];
    })];
});

$jumlahBaris = $perHari->max(fn ($baris) => $baris->keys()->max() ?? 0);
$jedaSenin = SchoolBreaks::forDay(DayOfWeek::Senin);
$adaSenin = $perHari->get(DayOfWeek::Senin->value)->isNotEmpty();

$grid = $jumlahBaris > 0
    ? collect(range(1, $jumlahBaris))->flatMap(function (int $nomor) use ($perHari, $jedaSenin, $adaSenin) {
        $baris = [[
            'type' => 'lesson',
            'nomor' => $nomor,
            'hari' => $perHari->map(fn ($baris) => $baris->get($nomor)),
        ]];

        if ($adaSenin) {
            foreach ($jedaSenin as $jeda) {
                if ($jeda['after'] === $nomor) {
                    $baris[] = ['type' => 'break', 'jeda' => $jeda];
                }
            }
        }

        return $baris;
    })
    : collect();
```

Add the import `use App\Support\SchoolBreaks;`.

- [ ] **Step 4: Render break rows in the Blade table**

Inside the `@forelse ($grid as $slot)` loop, wrap the existing lesson row with `@if ($slot['type'] === 'break')` and add a break row before the `@else`. The break row must be:

```blade
<tr>
    <th class="sticky left-0 z-10 border-b border-zinc-100 bg-zinc-50 px-3 py-2 text-left text-[11px] font-medium text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
        Jeda
    </th>
    <td colspan="5" class="border-b border-zinc-100 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-800/50">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $slot['jeda']['label'] }}</span>
            <span>·</span>
            <span>{{ str_replace(':', '.', $slot['jeda']['start']) }}–{{ str_replace(':', '.', $slot['jeda']['end']) }}</span>
            <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">Senin</span>
        </div>
    </td>
</tr>
```

The existing lesson row stays unchanged inside the `@else`.

- [ ] **Step 5: Run the new test to verify it passes**

Run: `php artisan test --filter=test_grid_menampilkan_jeda_istirahat_senin`

Expected: PASS.

- [ ] **Step 6: Run the full schedule and dark-mode tests**

Run: `php artisan test --filter='ScheduleManagementTest|ScheduleBreakTest|DarkModeTextContrastTest'`

Expected: PASS. The old `test_grid_menyusun_jam_per_hari_berdasarkan_urutan` must still pass because its 07:00 times fall back to lesson order.

- [ ] **Step 7: Format and commit**

Run: `vendor\bin\pint app/Livewire/ScheduleManagement.php tests/Feature/Livewire/ScheduleManagementTest.php`

```bash
git add app/Livewire/ScheduleManagement.php resources/views/livewire/schedule-management.blade.php tests/Feature/Livewire/ScheduleManagementTest.php
git commit -m "feat: show Monday breaks in schedule grid"
```

---

### Task 3: Final verification

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`

Expected: all tests pass.

- [ ] **Step 2: Build assets**

Run: `npm run build`

Expected: build succeeds.

- [ ] **Step 3: Check formatting**

Run: `vendor\bin\pint --test app/Support/SchoolBreaks.php app/Services/ScheduleManager.php app/Livewire/ScheduleManagement.php tests/Feature/ScheduleBreakTest.php tests/Feature/Livewire/ScheduleManagementTest.php`

Expected: PASS.

- [ ] **Step 4: Report**

Report the test count, build result, and the Monday-only limitation.
