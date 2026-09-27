<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriod;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\StudentGender;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data demo untuk_one sekolah: 1 admin, 10 guru, 7 kelas, 210 siswa, 8 mapel,
 * 105 jadwal, dan ±3.800 baris absensi pada 7 hari terakhir.
 */
class DemoSchoolSeeder extends Seeder
{
    private const KELAS = [
        ['VII-A', 7], ['VII-B', 7], ['VIII-A', 8],
        ['VIII-B', 8], ['IX-A', 9], ['IX-B', 9], ['IX-C', 9],
    ];

    private const MAPEL = [
        'Matematika' => 'MTK',
        'Bahasa Indonesia' => 'BINDO',
        'Bahasa Inggris' => 'BING',
        'Ilmu Pengetahuan Alam' => 'IPA',
        'Ilmu Pengetahuan Sosial' => 'IPS',
        'Pendidikan Agama' => 'PAI',
        'Pendidikan Fisika' => 'PFIS',
        'Pendidikan Pancasila dan Kewarganegaraan' => 'PPKN',
    ];

    /**
     * 7 kelas belajar bersamaan butuh minimal 7 guru berbeda per jam pelajaran.
     * Dengan 6 guru, kelas ke-1 dan ke-7 selalu dapat guru yang sama di jam
     * yang sama dan form jadwal tidak bisa diuji dengan data ini.
     */
    private const JUMLAH_GURU = 10;

    private const SISWA_PER_KELAS = 30;

    private const SLOT = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:00:00', '11:30:00'],
    ];

    /** Jumlah baris absensi per sekali insert. */
    private const UKURAN_CHUNK = 300;

    /** Rentang hari absensi yang dibuat, dihitung mundur dari hari ini. */
    private const JENDELA_HARI = 6;

    public function run(): void
    {
        $tahun = $this->buatTahunAjaran();

        User::create([
            'name' => 'Admin Sekolah',
            'nuptk' => '100000000000000001',
            'email' => 'admin@sekolah.sch.id',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        /** @var Collection<int, User> $guru */
        $guru = collect(range(1, self::JUMLAH_GURU))->map(fn (int $i) => User::create([
            'name' => "Guru {$i}",
            'nuptk' => '20'.str_pad((string) $i, 16, '0', STR_PAD_LEFT),
            'email' => "guru{$i}@sekolah.sch.id",
            'password' => 'password',
            'role' => UserRole::Guru,
            'is_active' => true,
        ]));

        /** @var Collection<int, Subject> $mapel */
        $mapel = collect(self::MAPEL)->map(fn (string $nama, string $kode) => Subject::create([
            'name' => $nama,
            'code' => $kode,
        ]))->values();

        /** @var Collection<int, SchoolClass> $kelas */
        $kelas = collect(self::KELAS)->map(fn (array $baris, int $i) => SchoolClass::create([
            'academic_year_id' => $tahun->id,
            'homeroom_teacher_id' => $guru[$i % self::JUMLAH_GURU]->id,
            'name' => $baris[0],
            'grade_level' => $baris[1],
        ]));

        $this->buatJadwal($kelas, $mapel, $guru);
        $this->buatSiswa($kelas);
        $this->buatAbsensi($kelas, $tahun);
    }

    /**
     * Tahun ajaran dibungkus di sekitar hari ini, jadi `is_active` selalu benar
     * dan rekap bulan berjalan selalu punya periode yang sah.
     */
    private function buatTahunAjaran(): AcademicYear
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        return AcademicYear::create([
            'name' => $tahunMulai.'/'.($tahunMulai + 1),
            'period' => now()->month >= 7 && now()->month <= 12
                ? AcademicPeriod::Ganjil
                : AcademicPeriod::Genap,
            'start_date' => sprintf('%d-07-01', $tahunMulai),
            'end_date' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_active' => true,
        ]);
    }

    /**
     * Guru untuk (kelas, hari, slot) diambil dari `($indexKelas + $indexHari +
     * $indexSlot) % 10`. Untuk satu hari dan satu slot, 7 nilai berurutan mod 10
     * tidak pernah bertabrakan, jadi tidak ada guru yang Mengajar dua kelas
     * pada jam yang sama.
     *
     * @param  Collection<int, SchoolClass>  $kelas
     * @param  Collection<int, Subject>  $mapel
     * @param  Collection<int, User>  $guru
     */
    private function buatJadwal(Collection $kelas, Collection $mapel, Collection $guru): void
    {
        $jumlahMapel = $mapel->count();

        foreach ($kelas->values() as $indexKelas => $k) {
            foreach (DayOfWeek::cases() as $indexHari => $hari) {
                foreach (self::SLOT as $indexSlot => $slot) {
                    Schedule::create([
                        'class_id' => $k->id,
                        'subject_id' => $mapel[($indexKelas * 3 + $indexSlot) % $jumlahMapel]->id,
                        'teacher_id' => $guru[($indexKelas + $indexHari + $indexSlot) % self::JUMLAH_GURU]->id,
                        'day_of_week' => $hari,
                        'start_time' => $slot[0],
                        'end_time' => $slot[1],
                    ]);
                }
            }
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $kelas
     */
    private function buatSiswa(Collection $kelas): void
    {
        $namaDepan = ['Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gilang', 'Hana', 'Indra', 'Jihan'];
        $urut = 0;

        foreach ($kelas as $k) {
            for ($i = 1; $i <= self::SISWA_PER_KELAS; $i++) {
                $urut++;

                Student::create([
                    'class_id' => $k->id,
                    // Nomor urut global, bukan crc32(): hash bisa bertabrakan
                    // antar kelas dan langsung melanggar unique constraint.
                    'nisn' => (string) (100000 + $urut),
                    'name' => $namaDepan[($i - 1) % count($namaDepan)].' '.$k->name,
                    'gender' => $i % 2 === 0 ? StudentGender::P : StudentGender::L,
                ]);
            }
        }
    }

    /**
     * Absensi hanya dibuat pada hari jadwalnya. Tanpa filter ini, jadwal hari
     * Senin akan punya absensi hari Selasa juga dan rekap harian langsung salah.
     *
     * @param  Collection<int, SchoolClass>  $kelas
     */
    private function buatAbsensi(Collection $kelas, AcademicYear $tahun): void
    {
        $selesai = min(now()->copy()->endOfDay(), $tahun->end_date->copy()->endOfDay());
        $mulai = max($tahun->start_date->copy()->startOfDay(), $selesai->copy()->subDays(self::JENDELA_HARI));

        if ($mulai->gt($selesai)) {
            return;
        }

        $status = [
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Izin,
            AttendanceStatus::Sakit,
            AttendanceStatus::Alpa,
        ];

        $siswaPerKelas = $kelas->mapWithKeys(fn (SchoolClass $k) => [
            $k->id => Student::where('class_id', $k->id)->get(),
        ]);

        $jadwal = Schedule::whereIn('class_id', $kelas->pluck('id'))->get();

        foreach ($jadwal as $j) {
            $siswa = $siswaPerKelas->get($j->class_id, collect());

            if ($siswa->isEmpty()) {
                continue;
            }

            for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
                if ($tanggal->dayOfWeek !== $j->day_of_week->value) {
                    continue;
                }

                $baris = [];

                foreach ($siswa->values() as $index => $murid) {
                    $statusHariIni = $status[($index + $tanggal->day) % count($status)];

                    $baris[] = [
                        'id' => (string) Str::uuid(),
                        'student_id' => $murid->id,
                        'schedule_id' => $j->id,
                        'date' => $tanggal->toDateString(),
                        'status' => $statusHariIni->value,
                        'note' => $statusHariIni === AttendanceStatus::Hadir ? null : 'Data demo',
                        'recorded_by' => $j->teacher_id,
                        'updated_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($baris, self::UKURAN_CHUNK) as $chunk) {
                    DB::table('attendances')->insert($chunk);
                }
            }
        }
    }
}
