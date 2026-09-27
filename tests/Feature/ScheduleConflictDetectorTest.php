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
