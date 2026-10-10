<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\UserManagement;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sidebar bawaan starter kit memanggil route('dashboard'). Route itu
        // baru dibuat di Task 6, jadi di sini dipasang route double supaya
        // kegagalan test menunjuk masalah UserManagement, bukan layout.
        if (! Route::has('dashboard')) {
            Route::get('/dashboard', fn (): string => 'ok')
                ->middleware(['web', 'auth', EnsureUserIsActive::class])
                ->name('dashboard');

            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['is_active' => true]);
    }

    public function test_hanya_admin_yang_bisa_membuka_halaman(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/user')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman(): void
    {
        $this->actingAs($this->admin())
            ->get('/user')
            ->assertOk()
            ->assertSee('Manajemen User');
    }

    public function test_daftar_user_muncul_dengan_pencarian(): void
    {
        $admin = $this->admin();

        User::factory()->guru()->create([
            'name' => 'Budi Santoso',
            'nuptk' => '100000000000000010',
        ]);

        User::factory()->guru()->create([
            'name' => 'Siti Aminah',
            'nuptk' => '100000000000000011',
        ]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Siti Aminah')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    /**
     * Flux hanya membuka <dialog> lewat event 'modal-show' yang namanya cocok
     * dengan atribut name milik modal. Menyalakan $showForm hanya membuat elemen
     * modal ikut ter-render, tidak membukanya, jadi tanpa dispatch ini klik
     * "Tambah User" terlihat seperti tidak terjadi apa-apa.
     */
    public function test_tambah_user_memerintahkan_modal_form_untuk_dibuka(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->assertSee('data-modal="'.UserManagement::FORM_MODAL.'"', escape: false)
            ->assertDispatched('modal-show', name: UserManagement::FORM_MODAL);
    }

    public function test_ubah_user_memerintahkan_modal_form_untuk_dibuka(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('edit', $target->id)
            ->assertSee('data-modal="'.UserManagement::FORM_MODAL.'"', escape: false)
            ->assertDispatched('modal-show', name: UserManagement::FORM_MODAL);
    }

    public function test_reset_password_memerintahkan_modal_reset_untuk_dibuka(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('startReset', $target->id)
            ->assertSee('data-modal="'.UserManagement::RESET_MODAL.'"', escape: false)
            ->assertDispatched('modal-show', name: UserManagement::RESET_MODAL);
    }

    public function test_admin_bisa_membuat_user_baru(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Dewi Lestari')
            ->set('form.nuptk', '100000000000000020')
            ->set('form.email', 'dewi@sekolah.sch.id')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'nuptk' => '100000000000000020',
            'name' => 'Dewi Lestari',
            'role' => UserRole::Guru->value,
            'is_active' => true,
        ]);
    }

    public function test_password_baru_disimpan_sebagai_hash(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Dewi Lestari')
            ->set('form.nuptk', '100000000000000020')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('nuptk', '100000000000000020')->firstOrFail();

        $this->assertTrue(Hash::check('rahasia-kuat-123', $user->password));
    }

    public function test_nuptk_duplikat_ditolak(): void
    {
        $admin = $this->admin();

        User::factory()->guru()->create(['nuptk' => '100000000000000030']);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Orang Lain')
            ->set('form.nuptk', '100000000000000030')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasErrors('form.nuptk');
    }

    public function test_nuptk_harus_18_digit_angka(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Test User')
            ->set('form.nuptk', '12345')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasErrors(['form.nuptk' => 'digits']);
    }

    public function test_password_konfirmasi_tidak_sama_ditolak(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Test User')
            ->set('form.nuptk', '100000000000000040')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'beda-sama-456')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_email_boleh_kosong(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Tanpa Email')
            ->set('form.nuptk', '100000000000000050')
            ->set('form.email', '')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'nuptk' => '100000000000000050',
            'email' => null,
        ]);
    }

    public function test_admin_bisa_mengubah_user_lama_tanpa_mengubah_password(): void
    {
        $admin = $this->admin();

        $target = User::factory()->guru()->create([
            'name' => 'Nama Lama',
            'nuptk' => '100000000000000060',
        ]);

        $passwordAwal = $target->password;

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('edit', $target->id)
            ->set('form.name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertSame('Nama Baru', $target->name);
        $this->assertSame($passwordAwal, $target->password);
    }

    public function test_menyimpan_form_tanpa_perubahan_nuptk_tidak_ditolak_sebagai_duplikat(): void
    {
        $admin = $this->admin();

        $target = User::factory()->guru()->create(['nuptk' => '100000000000000061']);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('edit', $target->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_admin_bisa_menonaktifkan_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('toggleActive', $target->id)
            ->assertHasNoErrors();

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_admin_tidak_bisa_menonaktifkan_dirinya_sendiri(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_aktif_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        $admin = $this->admin();

        $this->assertSame(1, User::where('role', UserRole::Admin->value)->count());

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_aktif_terakhir_tidak_bisa_dihapus(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('askDelete', $admin->id)
            ->call('confirmDelete');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_tidak_bisa_menghapus_dirinya_sendiri(): void
    {
        $admin = $this->admin();
        User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('askDelete', $admin->id)
            ->call('confirmDelete');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_bisa_menghapus_user_bukan_admin(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('askDelete', $target->id)
            ->call('confirmDelete')
            ->assertHasNoErrors();

        // Guru ini belum pernah mengajar ataupun mencatat absensi, jadi
        // tidak ada alasan untuk menyimpan barisnya.
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_menghapus_user_tidak_menghapus_absensi_yang_sudah_tercatat(): void
    {
        $admin = $this->admin();

        $teacher = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $mapel = Subject::factory()->create();
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        $absensi = Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => now()->startOfWeek()->addDay()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $teacher->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('askDelete', $teacher->id)
            ->call('confirmDelete')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('attendances', [
            'id' => $absensi->id,
            'recorded_by' => $teacher->id,
        ]);
    }

    public function test_user_berriwayat_absensi_menampilkan_tombol_arsipkan(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->guru()->create(['name' => 'Budi Santoso']);
        $kelas = SchoolClass::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => now()->startOfWeek()->addDay()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $teacher->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->assertSee('Arsipkan');
    }

    public function test_konfirmasi_hapus_user_berriwayat_menjelaskan_arsip_sebelum_aksi(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->guru()->create(['name' => 'Budi Santoso']);
        $kelas = SchoolClass::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => now()->startOfWeek()->addDay()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $teacher->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('askDelete', $teacher->id)
            ->assertSet('pendingDeletePermanent', false)
            ->assertSet('pendingDeleteMessage', 'Akun Budi Santoso diarsipkan, bukan dihapus permanen — masih dipakai riwayat absensi yang harus tetap terbaca.');

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'deleted_at' => null]);
    }

    public function test_user_terarsip_bisa_ditampilkan_dan_dipulihkan(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create(['name' => 'Budi Santoso']);
        $target->delete();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->set('showArchived', true)
            ->assertSee('Pulihkan')
            ->assertSee('Budi Santoso')
            ->call('pulihkan', $target->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_nuptk_user_yang_sudah_dihapus_tidak_bisa_dipakai_lagi(): void
    {
        $admin = $this->admin();

        $target = User::factory()->guru()->create(['nuptk' => '100000000000000070']);
        $target->delete();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('create')
            ->set('form.name', 'Pemakai NUPTK Lama')
            ->set('form.nuptk', '100000000000000070')
            ->set('form.role', UserRole::Guru->value)
            ->set('password', 'rahasia-kuat-123')
            ->set('password_confirmation', 'rahasia-kuat-123')
            ->call('save')
            ->assertHasErrors('form.nuptk');
    }

    /**
     * mount() menolak guru, jadi Livewire::test() tidak bisa dipakai untuk
     * menguji aksi di bawah ini. Pagar aksi diuji di lapis policy, yang persis
     * objek yang diterima $this->authorize() di setiap action.
     */
    public function test_guru_tidak_bisa_menjalankan_aksi_mutasi(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);
        $target = User::factory()->guru()->create();

        $gate = Gate::forUser($guru);

        $this->assertFalse($gate->allows('viewAny', User::class));
        $this->assertFalse($gate->allows('create', User::class));
        $this->assertFalse($gate->allows('update', $target));
        $this->assertFalse($gate->allows('delete', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_boleh_menjalankan_aksi_mutasi(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create();

        $gate = Gate::forUser($admin);

        $this->assertTrue($gate->allows('viewAny', User::class));
        $this->assertTrue($gate->allows('create', User::class));
        $this->assertTrue($gate->allows('update', $target));
        $this->assertTrue($gate->allows('delete', $target));
    }

    public function test_reset_password_menghasilkan_password_baru_yang_berfungsi(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create();

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('startReset', $target->id)
            ->set('password', 'password-baru-456')
            ->set('password_confirmation', 'password-baru-456')
            ->call('confirmReset')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('password-baru-456', $target->fresh()->password));
    }

    public function test_reset_password_menolak_konfirmasi_yang_tidak_sama(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create();
        $passwordAwal = $target->password;

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('startReset', $target->id)
            ->set('password', 'password-baru-456')
            ->set('password_confirmation', 'beda-sama-789')
            ->call('confirmReset')
            ->assertHasErrors('password');

        $this->assertSame($passwordAwal, $target->fresh()->password);
    }

    public function test_password_lama_tidak_berfungsi_setelah_reset(): void
    {
        $admin = $this->admin();
        $target = User::factory()->guru()->create([
            'password' => 'password-lama-123',
        ]);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('startReset', $target->id)
            ->set('password', 'password-baru-456')
            ->set('password_confirmation', 'password-baru-456')
            ->call('confirmReset')
            ->assertHasNoErrors();

        $this->assertFalse(Hash::check('password-lama-123', $target->fresh()->password));
    }

    public function test_halaman_menampilkan_user_yang_sudah_dinonaktifkan(): void
    {
        $admin = $this->admin();

        User::factory()->guru()->create([
            'name' => 'Guru Nonaktif',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get('/user')
            ->assertOk()
            ->assertSee('Guru Nonaktif')
            ->assertSee('Nonaktif');
    }
}
