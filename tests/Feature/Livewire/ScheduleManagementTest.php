<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Livewire\ScheduleManagement;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolBreak;
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
            ->set('start_time', '07:45')
            ->set('end_time', '09:15')
            ->call('save')
            ->assertHasNoErrors();

        $jadwal = Schedule::sole();

        $this->assertSame($kelas->id, $jadwal->class_id);
        $this->assertSame($mapel->id, $jadwal->subject_id);
        $this->assertSame($guru->id, $jadwal->teacher_id);
        $this->assertSame(DayOfWeek::Senin, $jadwal->day_of_week);
        $this->assertSame('07:45', $jadwal->start_time->format('H:i'));
        $this->assertSame('09:15', $jadwal->end_time->format('H:i'));
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
                'start_time' => '07:45',
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
            ->set('start_time', '07:45')
            ->set('end_time', '09:15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('07:45', $jadwal->fresh()->start_time->format('H:i'));
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

        $this->assertSame('break', $grid[0]['type']);
        $this->assertSame('Upacara', $grid[0]['jeda']->first()['label']);

        $pelajaran = $grid->where('type', 'lesson')->keyBy('nomor');

        $this->assertSame('Mapel A', $pelajaran[1]['hari'][DayOfWeek::Senin->value]->first()->subject->name);
        $this->assertSame('Mapel C', $pelajaran[1]['hari'][DayOfWeek::Selasa->value]->first()->subject->name);
        $this->assertSame('Mapel B', $pelajaran[2]['hari'][DayOfWeek::Senin->value]->first()->subject->name);
        $this->assertTrue($pelajaran[2]['hari'][DayOfWeek::Selasa->value]->isEmpty());
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
            ->assertSee('10.10–10.25')
            ->assertSee('Istirahat / Sholat')
            ->assertSee('12.20–12.50');
    }

    public function test_grid_menampilkan_upacara_diatas_jam_pertama(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();
        $mapel = Subject::factory()->create();

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:45',
            'end_time' => '08:20',
        ]);

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $this->assertSame('break', $grid[0]['type']);
        $this->assertSame('Upacara', $grid[0]['jeda']->first()['label']);
        $this->assertSame('lesson', $grid[1]['type']);
        $this->assertSame(1, $grid[1]['nomor']);
    }

    public function test_jam_mulai_di_luar_tabel_tetap_masuk_jam_terdekat_sebelumnya(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();

        foreach ([
            ['07:45', '08:20', 'Jam Satu'],
            ['08:20', '08:55', 'Jam Dua'],
            ['08:55', '09:30', 'Jam Tiga'],
            ['11:10', '11:45', 'Jam Enam'],
            ['11:45', '12:20', 'Jam Tujuh'],
            ['12:50', '13:35', 'Jam Delapan'],
            ['13:35', '14:20', 'Jam Sembilan'],
        ] as [$mulai, $selesai, $nama]) {
            Schedule::factory()->create([
                'class_id' => $kelas->id,
                'subject_id' => Subject::factory()->create(['name' => $nama])->id,
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::Senin,
                'start_time' => $mulai,
                'end_time' => $selesai,
            ]);
        }

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $perNomor = $grid->where('type', 'lesson')
            ->keyBy('nomor')
            ->map(fn ($slot) => $slot['hari'][DayOfWeek::Senin->value]
                ->map(fn (Schedule $jadwal) => $jadwal->subject->name)
                ->all());

        $this->assertSame(['Jam Tujuh'], $perNomor[7]);
        $this->assertSame(['Jam Sembilan'], $perNomor[9]);
    }

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

        $this->assertSame([DayOfWeek::Senin, DayOfWeek::Selasa], $barisJeda['jeda']->pluck('day')->all());
    }

    public function test_jeda_jumat_tampil_di_kolom_jumat(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create();
        SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Jumat,
            'label' => 'Yasinan Bersama',
            'start_time' => '07:00',
            'end_time' => '07:45',
            'after' => 0,
        ]);

        $html = Livewire::actingAs($admin)->test(ScheduleManagement::class)->html();

        $this->assertSame(1, preg_match('/data-jeda-hari="5"[^>]*>(.*?)<\/td>/s', $html, $jumat));
        $this->assertStringContainsString('Yasinan Bersama', $jumat[1]);

        $this->assertSame(1, preg_match('/data-jeda-hari="1"[^>]*>(.*?)<\/td>/s', $html, $senin));
        $this->assertStringNotContainsString('Yasinan Bersama', $senin[1]);
    }

    public function test_istirahat_tampil_di_setiap_hari(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create();

        $grid = Livewire::actingAs($admin)->test(ScheduleManagement::class)->viewData('grid');

        $hariJeda = $grid->where('type', 'break')
            ->flatMap(fn ($slot) => $slot['jeda'])
            ->filter(fn (array $jeda) => $jeda['label'] === 'Istirahat')
            ->pluck('day')
            ->unique()
            ->sortBy('value')
            ->values()
            ->all();

        $this->assertSame(DayOfWeek::cases(), $hariJeda);
    }

    public function test_jeda_sebelum_jam_pertama_tetap_tampil_tanpa_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolClass::factory()->create();
        SchoolBreak::where('after', '>', 0)->delete();

        $grid = Livewire::actingAs($admin)->test(ScheduleManagement::class)->viewData('grid');

        $this->assertSame('break', $grid->first()['type']);
        $this->assertSame(0, $grid->first()['jeda']->first()['after']);
    }

    public function test_hari_tanpa_jeda_tidak_ikut_di_baris_jeda(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '13:00',
            'end_time' => '13:15',
            'after' => 5,
        ]);

        $grid = Livewire::actingAs($admin)->test(ScheduleManagement::class)->viewData('grid');
        $baris = $grid->where('type', 'break')
            ->first(fn ($slot) => $slot['jeda']->first()['after'] === 5);

        $this->assertSame([DayOfWeek::Selasa], $baris['jeda']->pluck('day')->all());
    }

    public function test_grid_menaruh_jeda_setelah_jam_empat_dan_tujuh(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();
        $mapel = Subject::factory()->create();

        foreach ([
            ['07:45', '08:20'],
            ['08:20', '08:55'],
            ['08:55', '09:30'],
            ['09:30', '10:05'],
            ['10:35', '11:10'],
            ['11:10', '11:45'],
            ['11:45', '12:20'],
            ['12:50', '13:25'],
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

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $this->assertSame(
            [0, 2, 3, 4, 7],
            $grid->where('type', 'break')
                ->map(fn ($slot) => $slot['jeda']->first()['after'])
                ->values()
                ->all(),
        );
        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 8],
            $grid->where('type', 'lesson')->pluck('nomor')->values()->all(),
        );
    }

    public function test_grid_menaruh_jeda_setelah_jam_empat_dan_tujuh_meski_jam_bolong(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();
        $mapel = Subject::factory()->create();

        foreach ([
            ['07:45', '08:20'],
            ['08:20', '08:55'],
            ['11:10', '11:45'],
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

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $this->assertSame(
            [0, 2, 3, 4, 7],
            $grid->where('type', 'break')
                ->map(fn ($slot) => $slot['jeda']->first()['after'])
                ->values()
                ->all(),
        );
    }

    public function test_selasa_hanya_menampilkan_istirahat(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();
        $mapel = Subject::factory()->create();

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Selasa,
            'start_time' => '10:15',
            'end_time' => '10:45',
        ]);

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $this->assertSame(
            ['Istirahat', 'Istirahat / Sholat'],
            $grid->where('type', 'break')
                ->flatMap(fn ($slot) => $slot['jeda'])
                ->where('day', DayOfWeek::Selasa)
                ->pluck('label')
                ->all(),
        );
    }

    public function test_grid_tidak_kehilangan_mapel_saat_jam_bertabrakan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();

        foreach ([
            ['07:00', '07:35', 'Mapel Satu'],
            ['07:35', '08:10', 'Mapel Dua'],
            ['08:10', '08:45', 'Mapel Tiga'],
            ['08:55', '09:30', 'Mapel Empat'],
        ] as [$mulai, $selesai, $nama]) {
            Schedule::factory()->create([
                'class_id' => $kelas->id,
                'subject_id' => Subject::factory()->create(['name' => $nama])->id,
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::Senin,
                'start_time' => $mulai,
                'end_time' => $selesai,
            ]);
        }

        $grid = Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->viewData('grid');

        $subjects = $grid
            ->where('type', 'lesson')
            ->flatMap(fn ($slot) => $slot['hari']->flatMap(fn ($cell) => $cell))
            ->map(fn (Schedule $jadwal) => $jadwal->subject->name)
            ->values()
            ->all();

        $this->assertCount(4, $subjects);
        $this->assertContains('Mapel Satu', $subjects);
        $this->assertContains('Mapel Dua', $subjects);
        $this->assertContains('Mapel Tiga', $subjects);
        $this->assertContains('Mapel Empat', $subjects);
    }

    public function test_pencarian_tidak_mengosongkan_grid_kelas_terpilih(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $alpha = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'KELAS-ALPHA']);
        $beta = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'KELAS-BETA']);

        Schedule::factory()->create([
            'class_id' => $alpha->id,
            'subject_id' => Subject::factory()->create(['name' => 'Mapel Alpha'])->id,
        ]);
        Schedule::factory()->create([
            'class_id' => $beta->id,
            'subject_id' => Subject::factory()->create(['name' => 'Mapel Beta'])->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ScheduleManagement::class)
            ->set('academic_year_id', $tahun->id)
            ->set('selectedClassId', $alpha->id)
            ->set('searchJadwal', 'BETA')
            ->assertSee('Kelas KELAS-ALPHA')
            ->assertDontSee('Belum ada jadwal untuk kelas ini.');
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
