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

    /**
     * "Jadwal Saya Hari Ini" adalah jadwal mengajar milik guru yang login.
     * Admin tidak mengajar, jadi kartu itu tidak relevan untuknya.
     */
    public function test_dashboard_admin_tidak_menampilkan_jadwal_saya_hari_ini(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Jadwal Saya Hari Ini');
    }

    public function test_dashboard_guru_menampilkan_jadwal_saya_hari_ini(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Jadwal Saya Hari Ini');
    }

    public function test_jadwal_dengan_mapel_dan_kelas_diarsipkan_tetap_tampil_di_dashboard(): void
    {
        $tahun = $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $guru->id,
            'name' => 'VII-A',
        ]);
        $mapel = Subject::factory()->create(['name' => 'Matematika']);

        Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);

        $mapel->delete();
        $kelas->delete();

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('VII-A');
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

    /**
     * Kalimat empty state di dashboard dan di `/jadwal` sengaja sama. Spec §5.4
     * menetapkan kalimatnya sebagai "Tidak ada jadwal mengajar hari ini.", dan
     * kartu dashboard menampilkan fitur yang sama seperti di halaman jadwal —
     * dua kalimat berbeda untuk satu keadaan yang sama hanya membuat orang mengira
     * itu dua keadaan berbeda.
     */
    public function test_tanpa_jadwal_tampil_empty_state_bukan_error(): void
    {
        $this->aktifTahunAjaran();

        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
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

    public function test_dashboard_memakai_label_status_alfa(): void
    {
        $this->aktifTahunAjaran();
        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Alfa')
            ->assertDontSee('Alpa');
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

    public function test_admin_ringkasan_absensi_hari_ini_mencakup_semua_kelas(): void
    {
        $tahun = $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $jadwalA = $this->jadwalDiTahun($tahun, 'Matematika');
        $jadwalB = $this->jadwalDiTahun($tahun, 'Fisika');

        $siswaA = Student::factory()->count(3)->create(['class_id' => $jadwalA->class_id]);
        $siswaB = Student::factory()->create(['class_id' => $jadwalB->class_id]);

        $this->catat($jadwalA, $siswaA[0], today(), AttendanceStatus::Hadir);
        $this->catat($jadwalA, $siswaA[1], today(), AttendanceStatus::Izin);
        $this->catat($jadwalA, $siswaA[2], today()->subDay(), AttendanceStatus::Alpa);
        $this->catat($jadwalB, $siswaB, today(), AttendanceStatus::Hadir);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('counts', [
                'hadir' => 2,
                'izin' => 1,
                'sakit' => 0,
                'alpa' => 0,
            ]);
    }

    public function test_admin_ringkasan_absensi_mengabaikan_tahun_ajaran_lain(): void
    {
        $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $tahunLain = AcademicYear::factory()->create(['is_active' => false]);
        $jadwal = $this->jadwalDiTahun($tahunLain, 'Biologi');
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $this->catat($jadwal, $siswa, today(), AttendanceStatus::Hadir);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('counts', [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'alpa' => 0,
            ]);
    }

    public function test_admin_ringkasan_absensi_nol_saat_tahun_ajaran_belum_diatur(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('counts', [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'alpa' => 0,
            ]);
    }

    public function test_admin_melihat_grafik_absensi_di_dashboard(): void
    {
        $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Absensi 7 Hari Terakhir')
            ->assertSee('Absensi Hari Ini');
    }

    public function test_guru_tidak_melihat_grafik_absensi_di_dashboard(): void
    {
        $this->aktifTahunAjaran();
        $guru = User::factory()->guru()->create(['is_active' => true]);

        Livewire::actingAs($guru)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Absensi 7 Hari Terakhir')
            ->assertDontSee('Absensi Hari Ini');
    }

    public function test_grafik_tidak_muncul_saat_tahun_ajaran_belum_diatur(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Absensi 7 Hari Terakhir')
            ->assertDontSee('Absensi Hari Ini');
    }

    public function test_grafik_hari_ini_menghitung_status_absensi_hari_ini(): void
    {
        $tahun = $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $jadwal = $this->jadwalDiTahun($tahun, 'Matematika');

        $siswa = Student::factory()->count(3)->create(['class_id' => $jadwal->class_id]);
        $this->catat($jadwal, $siswa[0], today(), AttendanceStatus::Hadir);
        $this->catat($jadwal, $siswa[1], today(), AttendanceStatus::Hadir);
        $this->catat($jadwal, $siswa[2], today(), AttendanceStatus::Izin);
        $this->catat($jadwal, $siswa[0], today()->subDay(), AttendanceStatus::Alpa);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('chartToday', function (array $chart): bool {
                return $chart['data']['datasets'][0]['data'] === [2, 1, 0, 0];
            });
    }

    public function test_grafik_tujuh_hari_mencakup_hari_ini_sampai_enam_hari_kebelakang(): void
    {
        $tahun = $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $jadwal = $this->jadwalDiTahun($tahun, 'Matematika');
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $this->catat($jadwal, $siswa, today()->subDays(6), AttendanceStatus::Hadir);
        $this->catat($jadwal, $siswa, today(), AttendanceStatus::Izin);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('chartLast7Days', function (array $chart): bool {
                return $chart['data']['labels'] === ['22/09', '23/09', '24/09', '25/09', '26/09', '27/09', '28/09']
                    && $chart['data']['datasets'][0]['data'] === [1, 0, 0, 0, 0, 0, 0]
                    && $chart['data']['datasets'][1]['data'] === [0, 0, 0, 0, 0, 0, 1];
            });
    }

    public function test_grafik_tidak_menghitung_absensi_tahun_ajaran_non_aktif(): void
    {
        $tahun = $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $nonAktif = AcademicYear::factory()->create(['is_active' => false]);
        $jadwal = $this->jadwalDiTahun($nonAktif, 'Matematika');
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $this->catat($jadwal, $siswa, today(), AttendanceStatus::Hadir);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('chartToday', function (array $chart): bool {
                return $chart['data']['datasets'][0]['data'] === [0, 0, 0, 0];
            })
            ->assertViewHas('last7HasData', false);
    }

    public function test_grafik_menampilkan_pesan_kosong_saat_belum_ada_absensi(): void
    {
        $this->aktifTahunAjaran();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('Belum ada data absensi 7 hari terakhir.')
            ->assertSee('Belum ada data absensi hari ini.');
    }

    private function jadwalDiTahun(AcademicYear $tahun, string $namaMapel): Schedule
    {
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $mapel = Subject::factory()->create(['name' => $namaMapel]);

        return Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'day_of_week' => DayOfWeek::from(now()->dayOfWeekIso),
        ]);
    }

    private function catat(Schedule $jadwal, Student $siswa, Carbon $tanggal, AttendanceStatus $status): void
    {
        Attendance::query()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => $tanggal->toDateString(),
            'status' => $status,
            'recorded_by' => $jadwal->teacher_id,
        ]);
    }
}
