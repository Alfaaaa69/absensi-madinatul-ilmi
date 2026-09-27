<?php

namespace Tests\Feature\Livewire;

use App\Enums\DayOfWeek;
use App\Livewire\ScheduleManagement;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use App\Support\ScheduleConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $mapel = Subject::factory()->create();
        $guru = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', $kelas->id)
            ->set('subject_id', $mapel->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasNoErrors();

        $jadwal = Schedule::sole();

        $this->assertSame($kelas->id, $jadwal->class_id);
        $this->assertSame($mapel->id, $jadwal->subject_id);
        $this->assertSame($guru->id, $jadwal->teacher_id);
        $this->assertSame(DayOfWeek::Senin, $jadwal->day_of_week);
        $this->assertSame('07:00', $jadwal->start_time->format('H:i'));
        $this->assertSame('08:30', $jadwal->end_time->format('H:i'));
    }

    public function test_jadwal_bentrok_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '08:00')
            ->set('end_time', '09:00')
            ->call('save')
            ->assertHasErrors(['start_time']);

        $this->assertSame(1, Schedule::count());
    }

    public function test_pesan_bentrok_menyebut_kelas_dan_jam(): void
    {
        $guru = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        try {
            app(ScheduleManager::class)->save(null, [
                'class_id' => SchoolClass::factory()->create()->id,
                'subject_id' => $mapel->id,
                'teacher_id' => $guru->id,
                'day_of_week' => '1',
                'start_time' => '08:00',
                'end_time' => '09:00',
            ]);

            $this->fail('Jamnya bertindih, seharusnya ditolak.');
        } catch (ValidationException $e) {
            $pesan = $e->errors()['start_time'][0];

            $this->assertStringContainsString('VII-A', $pesan);
            $this->assertStringContainsString('Matematika', $pesan);
            $this->assertStringContainsString('Senin', $pesan);
            $this->assertStringContainsString('07:00-08:30', $pesan);
        }
    }

    public function test_jadwal_sepada_tetap_diterima(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '1')
            ->set('start_time', '08:30')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Schedule::count());
    }

    public function test_hari_di_luar_senin_jumat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', User::factory()->guru()->create()->id)
            ->set('day_of_week', '6')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasErrors(['day_of_week']);

        $this->assertSame(0, Schedule::count());
    }

    public function test_jam_selesai_harus_setelah_jam_mulai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', SchoolClass::factory()->create()->id)
            ->set('subject_id', Subject::factory()->create()->id)
            ->set('teacher_id', User::factory()->guru()->create()->id)
            ->set('day_of_week', '1')
            ->set('start_time', '10:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasErrors(['end_time']);

        $this->assertSame(0, Schedule::count());
    }

    public function test_mengubah_jadwal_tidak_bentrok_dengan_dirinya_sendiri(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->call('editForm', $jadwal->id)
            ->set('start_time', '07:30')
            ->set('end_time', '09:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('07:30', $jadwal->fresh()->start_time->format('H:i'));
    }

    public function test_arsipkan_jadwal_membebaskan_slot(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->call('arsipkan', $jadwal->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('schedules', ['id' => $jadwal->id]);

        $bentrok = app(ScheduleConflictDetector::class)
            ->findConflicts($guru, DayOfWeek::Senin->value, '07:00:00', '08:30:00');

        $this->assertSame([], $bentrok);
    }

    public function test_arsipkan_jadwal_tidak_menghapus_absensi_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)->test(ScheduleManagement::class)->call('arsipkan', $jadwal->id);

        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_guru_tidak_bisa_membuka_halaman_jadwal(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/jadwal')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_jadwal(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/jadwal')
            ->assertOk()
            ->assertSee('Jadwal Pelajaran');
    }
}
