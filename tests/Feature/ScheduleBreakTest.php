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
