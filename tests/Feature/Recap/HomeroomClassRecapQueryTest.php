<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Recap\Queries\HomeroomClassRecapQuery;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeroomClassRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_wali_kelas_hanya_melihat_kelasnya(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $miliknya = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $kelasOrangLain = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($miliknya->id, $hasil->classes->first()->id);
        $this->assertNotSame($kelasOrangLain->id, $hasil->classes->first()->id);
    }

    public function test_kelas_di_tahun_ajaran_berbeda_tidak_ikut_masuk(): void
    {
        $wali = User::factory()->guru()->create();
        $tahunIni = $this->tahun();
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);

        SchoolClass::factory()->create([
            'academic_year_id' => $tahunIni->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'homeroom_teacher_id' => $wali->id,
        ]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahunIni)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($tahunIni->id, $hasil->classes->first()->academic_year_id);
    }

    public function test_menjumlahkan_absensi_per_siswa(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $mapel = Subject::factory()->create();
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'subject_id' => $mapel->id,
            'teacher_id' => $wali->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        foreach ([
            ['2025-09-01', AttendanceStatus::Hadir],
            ['2025-09-02', AttendanceStatus::Hadir],
            ['2025-09-03', AttendanceStatus::Sakit],
            ['2025-09-04', AttendanceStatus::Alpa],
        ] as [$tanggal, $status]) {
            Attendance::create([
                'student_id' => $siswa->id,
                'schedule_id' => $jadwal->id,
                'date' => $tanggal,
                'status' => $status,
                'recorded_by' => $wali->id,
            ]);
        }

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->rows);
        $this->assertEmpty($hasil->subjects);

        $baris = $hasil->rows->first();

        $this->assertSame(2, $baris['total']['hadir']);
        $this->assertSame(0, $baris['total']['izin']);
        $this->assertSame(1, $baris['total']['sakit']);
        $this->assertSame(1, $baris['total']['alpa']);
        $this->assertSame(4, $baris['total']['total']);
        $this->assertSame(2, $hasil->summary['hadir']);
        $this->assertSame(4, array_sum($hasil->summary));
    }

    public function test_absensi_di_luar_periode_tidak_dihitung(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $wali->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-08-25',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $wali->id,
        ]);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertSame(0, $hasil->summary['hadir']);
    }

    public function test_absensi_di_masa_depan_tidak_dihitung(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));

        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();
        $kelas = SchoolClass::factory()->create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $wali->id,
        ]);
        $jadwal = Schedule::factory()->create([
            'class_id' => $kelas->id,
            'teacher_id' => $wali->id,
        ]);
        $siswa = Student::factory()->create(['class_id' => $kelas->id]);

        // Admin boleh mengisi tanggal yang belum terjadi. Hitungan rekap harus
        // tetap sama dengan kenyataan saat laporan dicetak.
        Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-09-10',
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $wali->id,
        ]);
        Attendance::create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => '2025-09-20',
            'status' => AttendanceStatus::Alpa,
            'recorded_by' => $wali->id,
        ]);

        $periode = RecapPeriod::monthly(2025, 9);

        $hasil = app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run($periode);

        $this->assertSame(1, $hasil->summary['hadir']);
        $this->assertSame(0, $hasil->summary['alpa']);

        // Drill-down harus menerapkan aturan yang sama, kalau tidak angka di
        // daftar riwayat dan angka di matriks akan berbeda.
        $riwayat = app(HomeroomClassRecapQuery::class)
            ->studentHistory($wali, $tahun, $siswa, $periode);

        $this->assertCount(1, $riwayat);
        $this->assertStringStartsWith('2025-09-10', (string) $riwayat->first()['date']);
    }

    public function test_periode_di_luar_tahun_ajaran_ditolak(): void
    {
        $wali = User::factory()->guru()->create();
        $tahun = $this->tahun();

        $this->expectException(RecapPeriodOutOfRange::class);

        app(HomeroomClassRecapQuery::class)
            ->for($wali, $tahun)
            ->run(RecapPeriod::monthly(2025, 7));
    }
}
