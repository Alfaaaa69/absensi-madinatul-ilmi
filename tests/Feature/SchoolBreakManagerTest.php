<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\SchoolBreak;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use App\Services\SchoolBreakManager;
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

        $this->assertSame(1, SchoolBreak::where('day_of_week', DayOfWeek::Selasa->value)->count());
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
