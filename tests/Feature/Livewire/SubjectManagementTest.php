<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\SubjectManagement;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_mapel(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subjects', ['code' => 'MTK', 'name' => 'Matematika']);
    }

    public function test_kode_mapel_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK')
            ->set('name', 'Matematika Dasar')
            ->call('save')
            ->assertHasErrors(['code']);

        $this->assertSame(1, Subject::count());
    }

    public function test_nama_mapel_duplikat_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK2')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(1, Subject::count());
    }

    public function test_mapel_tanpa_riwayat_dihapus_permanen(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->assertSee('Matematika')
            ->call('askDelete', $mapel->id)
            ->call('confirmDelete')
            ->assertDontSee('Matematika');

        $this->assertDatabaseMissing('subjects', ['id' => $mapel->id]);
    }

    public function test_mapel_yang_dipakai_absensi_menampilkan_tombol_arsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        Attendance::query()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $jadwal->teacher_id,
        ]);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->assertSee('Arsipkan')
            ->assertDontSee('Hapus');
    }

    public function test_konfirmasi_hapus_mapel_berriwayat_menjelaskan_arsip_sebelum_aksi(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->call('askDelete', $mapel->id)
            ->assertSet('pendingDeletePermanent', false)
            ->assertSet('pendingDeleteMessage', 'Mata pelajaran '.$mapel->name.' diarsipkan, bukan dihapus permanen. Jadwal dan absensi lamanya tetap utuh.');

        $this->assertDatabaseHas('subjects', ['id' => $mapel->id, 'deleted_at' => null]);
    }

    public function test_mapel_dengan_riwayat_absensi_hanya_diarsipkan(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $mapel = $jadwal->subject;
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $absensi = Attendance::factory()->create([
            'schedule_id' => $jadwal->id,
            'student_id' => $siswa->id,
        ]);

        Livewire::actingAs($admin)->test(SubjectManagement::class)->call('askDelete', $mapel->id)->call('confirmDelete');

        $this->assertSoftDeleted('subjects', ['id' => $mapel->id]);
        $this->assertDatabaseHas('attendances', ['id' => $absensi->id]);
        $this->assertDatabaseHas('schedules', ['id' => $jadwal->id]);
    }

    public function test_mapel_arsip_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        $mapel->delete();

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('showArchived', true)
            ->call('pulihkan', $mapel->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('subjects', ['id' => $mapel->id]);
    }

    public function test_mapel_diarsipkan_tidak_menghitung_ulang_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        $lama = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        $lama->delete();

        Livewire::actingAs($admin)
            ->test(SubjectManagement::class)
            ->set('code', 'MTK')
            ->set('name', 'Matematika')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Matematika', Subject::whereNull('deleted_at')->sole()->name);
    }

    public function test_guru_tidak_bisa_membuka_halaman_mapel(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/mapel')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_mapel(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/mapel')
            ->assertOk()
            ->assertSee('Mata Pelajaran');
    }
}
