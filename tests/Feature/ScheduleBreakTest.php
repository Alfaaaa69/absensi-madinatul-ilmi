<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
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

    public function test_jadwal_senin_yang_menabrak_upacara_ditolak(): void
    {
        try {
            app(ScheduleManager::class)->save(null, $this->data('1', '07:30', '08:00'));

            $this->fail('Jadwal seharusnya ditolak karena menabrak Upacara.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Jam 07:30–07:45 dipakai untuk Upacara.',
                $e->errors()['start_time'][0],
            );
        }

        $this->assertSame(0, Schedule::count());
    }

    public function test_jadwal_senin_tepat_setelah_upacara_diterima(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('1', '07:45', '08:20'));

        $this->assertSame(1, Schedule::count());
    }

    public function test_jadwal_senin_yang_diubah_ke_jam_istirahat_ditolak(): void
    {
        $data = $this->data('1', '09:30', '10:05');
        $jadwal = app(ScheduleManager::class)->save(null, $data);

        try {
            app(ScheduleManager::class)->save($jadwal, [
                ...$data,
                'start_time' => '10:15',
                'end_time' => '10:45',
            ]);

            $this->fail('Jadwal seharusnya ditolak karena menabrak istirahat.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Jam 10:05–10:35 dipakai untuk Istirahat.',
                $e->errors()['start_time'][0],
            );
        }

        $this->assertSame('09:30', $jadwal->fresh()->start_time->format('H:i'));
    }

    public function test_jadwal_senin_tepat_di_batas_jeda_tetap_diterima(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('1', '09:30', '10:05'));
        app(ScheduleManager::class)->save(null, $this->data('1', '10:35', '11:10'));

        $this->assertSame(2, Schedule::count());
    }

    public function test_jadwal_senin_tepat_di_batas_istirahat_kedua_diterima(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('1', '11:45', '12:20'));
        app(ScheduleManager::class)->save(null, $this->data('1', '12:50', '13:25'));

        $this->assertSame(2, Schedule::count());
    }

    public function test_pulihkan_jadwal_yang_menabrak_istirahat_ditolak(): void
    {
        $jadwal = Schedule::factory()->create([
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '10:15',
            'end_time' => '10:45',
        ]);
        $jadwal->delete();

        try {
            app(ScheduleManager::class)->pulihkan($jadwal);

            $this->fail('Jadwal seharusnya tidak dipulihkan karena menabrak istirahat.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Jam 10:05–10:35 dipakai untuk Istirahat.',
                $e->errors()['start_time'][0],
            );
        }

        $this->assertSoftDeleted('schedules', ['id' => $jadwal->id]);
    }

    public function test_jadwal_selasa_pada_jam_yang_sama_tidak_terkunci(): void
    {
        app(ScheduleManager::class)->save(null, $this->data('2', '10:15', '10:45'));

        $this->assertSame(1, Schedule::count());
    }
}
