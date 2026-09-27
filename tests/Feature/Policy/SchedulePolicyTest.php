<?php

namespace Tests\Feature\Policy;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_melihat_semua_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();

        $this->assertTrue(app(SchedulePolicy::class)->view($admin, $jadwal));
    }

    public function test_guru_melihat_jadwal_yang_dia_ajar(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Rabu,
        ]);

        $this->assertTrue(app(SchedulePolicy::class)->view($guru, $jadwal));
    }

    public function test_guru_tidak_melihat_jadwal_guru_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwalLain = Schedule::factory()->create();

        $this->assertFalse(app(SchedulePolicy::class)->view($guru, $jadwalLain));
    }

    public function test_guru_merekam_absensi_jadwalnya_hari_ini(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->recordAttendance($guru, $jadwal, Carbon::today()));
        $this->assertTrue($policy->recordAttendance($guru, $jadwal, Carbon::today()->subDay()));
        $this->assertFalse($policy->recordAttendance($guru, $jadwal, Carbon::today()->subDays(2)));
        $this->assertFalse($policy->recordAttendance($guru, $jadwal, Carbon::today()->addDay()));
    }

    public function test_guru_tidak_merekam_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create();

        $this->assertFalse(app(SchedulePolicy::class)->recordAttendance($guru, $jadwal, Carbon::today()));
    }

    public function test_admin_merekam_absensi_kapan_saja(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();

        $policy = app(SchedulePolicy::class);

        $this->assertTrue($policy->recordAttendance($admin, $jadwal, Carbon::today()->subYear()));
        $this->assertTrue($policy->recordAttendance($admin, $jadwal, Carbon::today()->addYear()));
    }
}
