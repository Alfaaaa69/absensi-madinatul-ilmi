<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Livewire\Dashboard;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Query jadwal memakai now()->dayOfWeekIso, dan DayOfWeek hanya punya 1-5.
     * Kalau suite ini dijalankan hari Sabtu atau Minggu, iso 6/7 tidak ada di
     * enum dan DayOfWeek::from() meledak. Waktu dibekukan ke Senin supaya
     * hasilnya sama di machine hari apa pun.
     */
    private const SENIN = '2026-09-28 07:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::SENIN, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function aktifTahunAjaran(): AcademicYear
    {
        return AcademicYear::factory()->create(['is_active' => true]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_akun_nonaktif_tidak_bisa_membuka_dashboard(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => false]);

        $this->actingAs($guru)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_melihat_jumlah_kelas_seluruh_sekolah(): void
    {
        $this->aktifTahunAjaran();

        $admin = User::factory()->admin()->create(['is_active' => true]);

        SchoolClass::factory()->count(3)->create();

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('homeroomCount', 3);
    }

    public function test_guru_hanya_melihat_kelas_miliknya_sendiri(): void
    {
        $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);

        SchoolClass::factory()->count(2)->create(['homeroom_teacher_id' => $guru->id]);
        SchoolClass::factory()->count(3)->create();

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('homeroomCount', 2);
    }

    public function test_guru_bukan_wali_kelas_melihat_nol_kelas(): void
    {
        $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        SchoolClass::factory()->count(4)->create();

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('homeroomCount', 0);
    }

    public function test_dashboard_menampilkan_jadwal_guru_hari_ini(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $guru->id,
        ]);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee($kelas->name);
    }

    public function test_jadwal_hari_lain_tidak_muncul(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $mapel = Subject::factory()->create(['name' => 'Sejarah']);

        $besok = DayOfWeek::from(now()->dayOfWeekIso === 5 ? 1 : now()->dayOfWeekIso + 1);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => $besok,
        ]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Sejarah');
    }

    public function test_jadwal_guru_lain_tidak_muncul(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $guruLain = User::factory()->guru()->create();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $mapel = Subject::factory()->create(['name' => 'Biologi']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guruLain->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Biologi');
    }

    public function test_tanpa_jadwal_tampil_empty_state_bukan_error(): void
    {
        $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal hari ini');
    }

    public function test_ringkasan_absensi_hari_ini_dihitung_per_status(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $mapel = Subject::factory()->create();

        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);

        // Empat catatan absensi (hadir, hadir, izin, alpa) jadi butuh empat
        // siswa. Plan asli hanya membuat tiga, lalu mengiterasi empat status.
        $siswa = Student::factory()->count(4)->create(['class_id' => $kelas->id]);

        foreach ([
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Izin,
            AttendanceStatus::Alpa,
        ] as $index => $status) {
            Attendance::factory()->create([
                'student_id' => $siswa[$index]->id,
                'schedule_id' => $jadwal->id,
                'date' => today()->toDateString(),
                'status' => $status,
                'recorded_by' => $guru->id,
            ]);
        }

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('counts', [
                'hadir' => 2,
                'izin' => 1,
                'sakit' => 0,
                'alpa' => 1,
            ]);
    }

    public function test_absensi_hari_lain_tidak_dihitung(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $mapel = Subject::factory()->create();

        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);

        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today()->subDay()->toDateString(),
            'status' => AttendanceStatus::Alpa,
            'recorded_by' => $guru->id,
        ]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('counts', [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'alpa' => 0,
            ]);
    }

    public function test_tahun_ajaran_aktif_selalu_ditampilkan(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('academicYear')
            ->assertSee($tahun->name)
            ->assertSee($tahun->period->value);
    }

    public function test_tanpa_tahun_ajaran_aktif_menghasilkan_pesan_yang_jelas(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Tahun ajaran aktif belum diatur');
    }
}
