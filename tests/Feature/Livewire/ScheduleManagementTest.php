<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Livewire\ScheduleManagement;
use App\Models\AcademicYear;
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

    public function test_pesan_bentrok_menyebut_semua_kelas_saat_guru_punya_beberapa_jadwal(): void
    {
        $guru = User::factory()->guru()->create();
        $kelasA = SchoolClass::factory()->create(['name' => 'VII-A']);
        $kelasB = SchoolClass::factory()->create(['name' => 'VII-B']);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelasA->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
        ]);

        Schedule::factory()->create([
            'class_id' => $kelasB->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        try {
            app(ScheduleManager::class)->save(null, [
                'class_id' => SchoolClass::factory()->create()->id,
                'subject_id' => $mapel->id,
                'teacher_id' => $guru->id,
                'day_of_week' => '1',
                'start_time' => '07:30',
                'end_time' => '09:30',
            ]);

            $this->fail('Jamnya bertindih dengan dua jadwal, seharusnya ditolak.');
        } catch (ValidationException $e) {
            $pesan = $e->errors()['start_time'][0];

            $this->assertStringContainsString('VII-A', $pesan);
            $this->assertStringContainsString('VII-B', $pesan);
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

    public function test_jadwal_tanpa_absensi_dihapus_permanen_dan_membebaskan_slot(): void
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
            ->call('askDelete', $jadwal->id)
            ->call('confirmDelete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('schedules', ['id' => $jadwal->id]);

        $bentrok = app(ScheduleConflictDetector::class)
            ->findConflicts($guru, DayOfWeek::Senin->value, '07:00:00', '08:30:00');

        $this->assertSame([], $bentrok);
    }

    public function test_jadwal_berisi_absensi_hanya_diarsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)->test(ScheduleManagement::class)->call('askDelete', $jadwal->id)->call('confirmDelete');

        $this->assertSoftDeleted('schedules', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
    }

    public function test_jadwal_yang_dipakai_absensi_menampilkan_tombol_arsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        Attendance::query()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $jadwal->teacher_id,
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->assertSee('Arsipkan')
            ->assertDontSee('Hapus');
    }

    public function test_konfirmasi_hapus_jadwal_berriwayat_menjelaskan_arsip_sebelum_aksi(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->call('askDelete', $jadwal->id)
            ->assertSet('pendingDeletePermanent', false)
            ->assertSet('pendingDeleteMessage', 'Jadwal diarsipkan, bukan dihapus permanen. Absensi lama pada jadwal ini tetap utuh.');

        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id, 'deleted_at' => null]);
    }

    public function test_jadwal_dengan_guru_diarsipkan_tetap_menampilkan_nama_guru(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['name' => 'Budi Santoso']);

        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        // Guru diarsipkan, tapi jadwal dan riwayat absensinya harus tetap utuh.
        $guru->delete();

        $this->actingAs($admin)
            ->get('/master/jadwal')
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    public function test_jadwal_dengan_kelas_dan_mapel_diarsipkan_tetap_bisa_dirender(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create(['name' => 'VII-A']);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => User::factory()->guru()->create()->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        $kelas->delete();
        $mapel->delete();

        $this->actingAs($admin)
            ->get('/master/jadwal')
            ->assertOk()
            ->assertSee('VII-A')
            ->assertSee('Matematika');
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

    public function test_grid_menyusun_jam_per_hari_berdasarkan_urutan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();
        $mapelA = Subject::factory()->create(['name' => 'Mapel A']);
        $mapelB = Subject::factory()->create(['name' => 'Mapel B']);
        $mapelC = Subject::factory()->create(['name' => 'Mapel C']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelA->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00',
            'end_time' => '07:35',
        ]);
        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelB->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:35',
            'end_time' => '08:10',
        ]);
        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapelC->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $this->assertCount(2, $grid);
        $this->assertSame('Mapel A', $grid[0]['hari'][DayOfWeek::Senin->value]->subject->name);
        $this->assertSame('Mapel C', $grid[0]['hari'][DayOfWeek::Selasa->value]->subject->name);
        $this->assertSame('Mapel B', $grid[1]['hari'][DayOfWeek::Senin->value]->subject->name);
        $this->assertNull($grid[1]['hari'][DayOfWeek::Selasa->value]);
    }

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

    public function test_guru_bisa_mengajar_hingga_tiga_kelas_berbeda(): void
    {
        $tahun = AcademicYear::factory()->create(['is_active' => true]);
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->count(3)->create(['academic_year_id' => $tahun->id]);
        $guru = User::factory()->guru()->create();
        $mapel = Subject::factory()->create();

        Schedule::factory()->create([
            'class_id' => $kelas[0]->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);
        Schedule::factory()->create([
            'class_id' => $kelas[1]->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '07:00',
            'end_time' => '08:30',
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', $kelas[2]->id)
            ->set('subject_id', $mapel->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '3')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(3, Schedule::where('teacher_id', $guru->id)->count());
    }

    public function test_guru_menambah_kelas_keempat_pada_tahun_ajaran_sama_ditolak(): void
    {
        $tahun = AcademicYear::factory()->create(['is_active' => true]);
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->count(4)->create(['academic_year_id' => $tahun->id]);
        $guru = User::factory()->guru()->create(['name' => 'Pak Joko']);
        $mapel = Subject::factory()->create();

        foreach ([0, 1, 2] as $i) {
            Schedule::factory()->create([
                'class_id' => $kelas[$i]->id,
                'subject_id' => $mapel->id,
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::cases()[$i],
                'start_time' => '07:00',
                'end_time' => '08:30',
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', $kelas[3]->id)
            ->set('subject_id', $mapel->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '4')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasErrors(['class_id'])
            ->assertSeeHtml('sudah mengajar 3 kelas berbeda');
    }

    public function test_guru_mengajar_jadwal_lain_di_kelas_yang_sama_tidak_dihitung_sebagai_kelas_baru(): void
    {
        $tahun = AcademicYear::factory()->create(['is_active' => true]);
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->count(3)->create(['academic_year_id' => $tahun->id]);
        $guru = User::factory()->guru()->create();
        $mapelA = Subject::factory()->create();
        $mapelB = Subject::factory()->create();

        foreach ([0, 1, 2] as $i) {
            Schedule::factory()->create([
                'class_id' => $kelas[$i]->id,
                'subject_id' => $mapelA->id,
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::cases()[$i],
                'start_time' => '07:00',
                'end_time' => '08:30',
            ]);
        }

        // Tambah jadwal lain di kelas 0 (yang sudah diajar) -> harus sukses
        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('class_id', $kelas[0]->id)
            ->set('subject_id', $mapelB->id)
            ->set('teacher_id', $guru->id)
            ->set('day_of_week', '4')
            ->set('start_time', '07:00')
            ->set('end_time', '08:30')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_search_jadwal_menyaring_daftar(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $kelasAlpha = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'KELAS-ALPHA']);
        $kelasBeta = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'KELAS-BETA']);
        Schedule::factory()->create(['class_id' => $kelasAlpha->id]);
        Schedule::factory()->create(['class_id' => $kelasBeta->id]);

        $daftar = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('searchJadwal', 'ALPHA')
            ->viewData('daftar');

        $this->assertCount(1, $daftar);
        $this->assertSame($kelasAlpha->id, $daftar->first()->class_id);
    }

    public function test_dropdown_mapel_menampilkan_semua_mapel(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $terjadwal = Subject::factory()->create(['name' => 'Mapel Terjadwal']);
        Subject::factory()->create(['name' => 'Mapel Belum Terjadwal']);
        Schedule::factory()->create(['class_id' => $kelas->id, 'subject_id' => $terjadwal->id]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('class_id', $kelas->id)
            ->assertSee('Mapel Terjadwal')
            ->assertSee('Mapel Belum Terjadwal');
    }

    public function test_jam_selesai_otomatis_45_menit_dari_jam_mulai(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('start_time', '07:00')
            ->assertSet('end_time', '07:45')
            ->set('start_time', '10:20')
            ->assertSet('end_time', '11:05');
    }
}
