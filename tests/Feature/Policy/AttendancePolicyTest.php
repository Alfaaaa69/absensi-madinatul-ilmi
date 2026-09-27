<?php

namespace Tests\Feature\Policy;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_mengedit_absensi_jadwalnya_hari_ini(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create(['date' => today(), 'schedule_id' => $jadwal->id]);

        $this->assertTrue(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_mengedit_absensi_kemarin(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->subDay(),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertTrue(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_tidak_mengedit_absensi_dua_hari_lalu(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->subDays(2),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_tidak_mengedit_absensi_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $absensi = Attendance::factory()->create([
            'date' => today()->addDay(),
            'schedule_id' => $jadwal->id,
        ]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_guru_mengedit_absensi_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $absensi = Attendance::factory()->create(['date' => today()]);

        $this->assertFalse(app(AttendancePolicy::class)->update($guru, $absensi));
    }

    public function test_admin_mengedit_absensi_kapan_saja(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = app(AttendancePolicy::class);

        foreach ([
            today()->subYear(),
            today()->subDays(2),
            today(),
            today()->addYear(),
        ] as $tanggal) {
            $absensi = Attendance::factory()->create(['date' => $tanggal]);

            $this->assertTrue($policy->update($admin, $absensi));
        }
    }
}
