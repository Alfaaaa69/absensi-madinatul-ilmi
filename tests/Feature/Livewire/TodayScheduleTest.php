<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Livewire\TodaySchedule;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const SENIN = '2026-09-28';

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

    private function masukSebagaiGuru(): User
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru);

        return $guru;
    }

    public function test_menampilkan_jadwal_hari_ini_dengan_jumlah_siswa(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);
        Student::factory()->count(3)->create(['class_id' => $jadwal->class_id]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Senin, 28 September 2026')
            ->assertSee('07:00 - 08:30')
            ->assertSee($jadwal->subject->name)
            ->assertSee('Kelas '.$jadwal->schoolClass->name.' - 3 siswa')
            ->assertSee('Belum absen');
    }

    public function test_mapel_dan_kelas_diarsipkan_tidak_membuat_halaman_jadwal_error(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        Student::factory()->count(2)->create(['class_id' => $jadwal->class_id]);

        $namaMapel = $jadwal->subject->name;
        $namaKelas = $jadwal->schoolClass->name;

        $jadwal->subject->delete();
        $jadwal->schoolClass->delete();

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee($namaMapel)
            ->assertSee('Kelas '.$namaKelas.' - 2 siswa');
    }

    public function test_jadwal_guru_lain_tidak_muncul(): void
    {
        $this->masukSebagaiGuru();
        Schedule::factory()->create([
            'teacher_id' => User::factory()->guru()->create()->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }

    public function test_jadwal_hari_lain_tidak_muncul(): void
    {
        $guru = $this->masukSebagaiGuru();
        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Selasa,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }

    public function test_mengurutkan_jadwal_menurut_jam_mulai(): void
    {
        $guru = $this->masukSebagaiGuru();

        foreach ([
            ['10:15:00', '11:45:00'],
            ['07:00:00', '08:30:00'],
            ['09:00:00', '10:00:00'],
        ] as [$mulai, $selesai]) {
            Schedule::factory()->create([
                'teacher_id' => $guru->id,
                'day_of_week' => DayOfWeek::Senin,
                'start_time' => $mulai,
                'end_time' => $selesai,
            ]);
        }

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSeeInOrder(['07:00 - 08:30', '09:00 - 10:00', '10:15 - 11:45']);
    }

    public function test_status_sudah_absen_muncul_setelah_baris_terisi(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Belum absen');

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Sudah absen')
            ->assertDontSee('Belum absen');
    }

    public function test_hari_sabtu_menampilkan_empty_state(): void
    {
        $guru = $this->masukSebagaiGuru();
        Carbon::setTestNow(Carbon::parse('2026-09-26', 'Asia/Jakarta'));

        Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        Livewire::test(TodaySchedule::class)
            ->assertOk()
            ->assertSee('Sabtu, 26 September 2026')
            ->assertSee('Tidak ada jadwal mengajar hari ini.');
    }

    /**
     * Semua test lain di file ini memakai Livewire::test(), yang merender komponen
     * tanpa layout. Rute HTTP-nya sendiri — termasuk sidebar dan middleware — belum
     * pernah diuji, jadi kesalahan di sana tidak akan terlihat di sini.
     */
    public function test_halaman_jadwal_bisa_diakses_lewat_http(): void
    {
        $this->masukSebagaiGuru();

        $this->get(route('jadwal.index'))->assertOk();
    }

    /**
     * `absensi.show` pernah 500 di setiap request: ImplicitRouteBinding
     * memotong nama parameter rute dengan nama properti publik bertipe
     * Carbon, lalu memanggil resolveRouteBinding() pada Carbon. Rute harus
     * benar-benar didispatch supaya guard ini menangkapnya kembali —
     * PHPStan dan Pint tidak akan.
     */
    public function test_rute_absensi_dengan_tanggal_tidak_menghasilkan_error_server(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'day_of_week' => DayOfWeek::Senin,
        ]);

        $this->get(route('absensi.show', [$jadwal, today()->toDateString()]))
            ->assertOk();
    }
}
