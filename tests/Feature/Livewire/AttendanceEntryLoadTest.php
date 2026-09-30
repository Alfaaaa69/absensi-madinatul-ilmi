<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryLoadTest extends TestCase
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

    private function jadwalDenganSiswa(int $jumlahSiswa = 3): array
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $jadwal->class_id,
                'name' => 'Siswa '.$urutan,
            ]);
        }

        $this->actingAs($guru);

        return [$guru, $jadwal];
    }

    public function test_tanggal_terkunci_ke_hari_ini(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Tanggal absensi dikunci')
            ->assertSee('Senin, 28 September 2026')
            ->assertSet('tanggal', today());
    }

    public function test_tidak_ada_input_tanggal_yang_bisa_diubah(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertDontSee('type="date"', false)
            ->assertDontSee('name="tanggal"', false);
    }

    public function test_menampilkan_jam_jadwal_besar_di_atas_form(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('07:00 - 08:30')
            ->assertSee($jadwal->subject->name)
            ->assertSee('Kelas '.$jadwal->schoolClass->name);
    }

    public function test_semua_siswa_default_hadir_dan_terurut_nama(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()
            ->where('class_id', $jadwal->class_id)
            ->orderBy('name')
            ->pluck('id');

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSeeInOrder(['Siswa 1', 'Siswa 2', 'Siswa 3']);

        foreach ($siswa as $id) {
            $component->assertSet('status.'.$id, 'hadir');
        }
    }

    public function test_absensi_lama_dimuat_bukan_form_kosong(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Sakit,
            'note' => 'Demam sejak pagi',
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSet('sudahDisimpan', true)
            ->assertSet('status.'.$siswa->id, 'sakit')
            ->assertSet('note.'.$siswa->id, 'Demam sejak pagi');
    }

    public function test_tombol_menjadi_simpan_perubahan_setelah_absensi_ada(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Absensi')
            ->assertDontSee('Simpan Perubahan');

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('Simpan Absensi');
    }
}
