<?php

namespace Tests\Feature\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceRecorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string,AttendanceStatus>  $statusPerSiswa
     * @return Collection<int, array{student_id: string, status: AttendanceStatus, note: null}>
     */
    private function rows(array $statusPerSiswa): Collection
    {
        return collect($statusPerSiswa)->map(fn ($status, $studentId) => [
            'student_id' => $studentId,
            'status' => $status,
            'note' => null,
        ])->values();
    }

    public function test_menyimpan_absensi_baru_mengisi_recorded_by(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $jumlah = app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru
        );

        $this->assertSame(1, $jumlah);

        $absensi = Attendance::first();

        $this->assertSame($guru->id, $absensi->recorded_by);
        $this->assertNull($absensi->updated_by);
    }

    public function test_submit_dua_kali_tidak_menggandakan_baris(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);
        $rows = $this->rows([$siswa->id => AttendanceStatus::Hadir]);

        $recorder->record($jadwal, Carbon::today(), $rows, $guru);
        $recorder->record($jadwal, Carbon::today(), $rows, $guru);

        $this->assertSame(1, Attendance::count());
    }

    public function test_koreksi_mengisi_updated_by(): void
    {
        $guru = User::factory()->guru()->create();
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);

        $recorder->record($jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru);
        $jumlah = $recorder->record($jadwal, Carbon::today(), $this->rows([$siswa->id => AttendanceStatus::Sakit]), $admin);

        $this->assertSame(0, $jumlah, 'Koreksi tidak boleh dihitung sebagai baris baru');

        $absensi = Attendance::first();

        $this->assertSame(AttendanceStatus::Sakit, $absensi->status);
        $this->assertSame($guru->id, $absensi->recorded_by);
        $this->assertSame($admin->id, $absensi->updated_by);
    }

    public function test_guru_tidak_bisa_merekam_tanggal_lampau(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today()->subDays(3), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $guru
        );
    }

    public function test_guru_tidak_bisa_merekam_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today()->addDay(), collect(), $guru);
    }

    public function test_guru_tidak_bisa_merekam_jadwal_orang_lain(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), collect(), $guru);
    }

    public function test_admin_merekam_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        $jumlah = app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today()->subYear(), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $admin
        );

        $this->assertSame(1, $jumlah);
    }

    public function test_siswa_dari_kelas_lain_ditolak(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswaLain = Student::factory()->create();

        $this->expectException(ValidationException::class);

        app(AttendanceRecorder::class)->record(
            $jadwal, Carbon::today(), $this->rows([$siswaLain->id => AttendanceStatus::Hadir]), $guru
        );
    }

    public function test_kegagalan_satu_siswa_membatalkan_seluruh_batch(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $sa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $as = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $asing = Student::factory()->create();

        $rows = $this->rows([
            $sa->id => AttendanceStatus::Hadir,
            $asing->id => AttendanceStatus::Hadir,
            $as->id => AttendanceStatus::Hadir,
        ]);

        try {
            app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), $rows, $guru);
            $this->fail('Harus melempar ValidationException');
        } catch (ValidationException) {
            // diharapkan
        }

        $this->assertSame(0, Attendance::count(), 'Batch harus rollback utuh');
    }

    public function test_catatan_tersimpan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        app(AttendanceRecorder::class)->record($jadwal, Carbon::today(), collect([[
            'student_id' => $siswa->id,
            'status' => AttendanceStatus::Sakit,
            'note' => 'Demam',
        ]]), $guru);

        $this->assertSame('Demam', Attendance::first()->note);
    }

    public function test_baris_lama_menghormati_batas_edit_guru(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);
        $recorder = app(AttendanceRecorder::class);

        $admin = User::factory()->admin()->create();
        $recorder->record($jadwal, Carbon::today()->subDays(5), $this->rows([$siswa->id => AttendanceStatus::Hadir]), $admin);

        $this->expectException(AuthorizationException::class);

        $recorder->record($jadwal, Carbon::today()->subDays(5), $this->rows([$siswa->id => AttendanceStatus::Alpa]), $guru);
    }
}
