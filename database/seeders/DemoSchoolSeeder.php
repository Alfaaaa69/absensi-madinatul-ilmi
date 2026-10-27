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

    private const NAMA_DEPAN = [
        'Ahmad', 'Muhammad', 'Mohamad', 'Agus', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri',
        'Gilang', 'Hana', 'Indra', 'Jihan', 'Kurnia', 'Lina', 'Mita', 'Nur', 'Oki', 'Putra',
        'Rudi', 'Santi', 'Toni', 'Udin', 'Wahyu', 'Xavier', 'Yudi', 'Zaki', 'Ade', 'Bayu',
        'Cakra', 'Darma', 'Edi', 'Felix', 'Ganesh', 'Hesti', 'Irfan', 'Juandra', 'Karim',
        'Lala', 'Mila', 'Nando', 'Okto', 'Prima', 'Quin', 'Rahmat', 'Sari', 'Tamara', 'Umar',
    ];

    private const NAMA_BELAKANG = [
        'Saputra', 'Sutrisno', 'Wijaya', 'Santoso', 'Pratama', 'Nugraha', 'Setiawan',
        'Kusuma', 'Maulana', 'Hidayat', 'Permana', 'Gunawan', 'Wibowo', 'Saputro',
        'Pamungkas', 'Susanto', 'Ferdiansyah', 'Sulistyo', 'Nuryanto', 'Kuswandari',
        'Suryani', 'Lestari', 'Anggraini', 'Dewanti', 'Rahmawati', 'Sulistyorini',
        'Puspasari', 'Mulyani', 'Setyawati', 'Wulandari', 'Mutiara', 'Ningsih', 'Wulani',
        'Rahayu', 'Ananda', 'Sari', 'Putri', 'Dian', 'Lestari', 'Anggraeni',
    ];

    private const SLOT = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:00:00', '11:30:00'],
    ];

    /**
     * Pemetaan indeks guru per slot mingguan (0..14) dan indeks kelas (0..6).
     * Setiap guru mengajar maksimal 3 kelas berbeda dan tidak ada jadwal bentrok
     * pada slot jam yang sama.
     */
    private const CLASS_SLOTS = array (
      array ( 1, 2, 6, 8, 9, 11, 13, 14 ),
      array ( 1, 2, 3, 4, 5, 7, 8, 10 ),
      array ( 1, 3, 6, 7, 8, 11, 12, 13 ),
      array ( 0, 1, 2, 4, 8, 9, 12, 14 ),
      array ( 0, 1, 2, 5, 9, 11, 13, 14 ),
      array ( 1, 2, 3, 8, 9, 11, 13, 14 ),
      array ( 0, 4, 6, 9, 11, 12, 13, 14 ),
    );

    private const CLASS_SUBJECTS = array (
      array ( 5, 4, 3, 1, 6, 2, 0, 7 ),
      array ( 7, 1, 2, 0, 4, 6, 3, 5 ),
      array ( 2, 5, 0, 1, 6, 4, 7, 3 ),
      array ( 3, 6, 7, 5, 0, 2, 4, 1 ),
      array ( 7, 2, 6, 1, 0, 5, 4, 3 ),
      array ( 1, 4, 0, 5, 2, 3, 6, 7 ),
      array ( 4, 3, 0, 6, 1, 7, 2, 5 ),
    );

    private const SLOT_ASSIGNMENTS = array (
      0 => array ( 3 => 8, 4 => 9, 6 => 6 ),
      1 => array ( 0 => 0, 1 => 8, 2 => 1, 3 => 2, 4 => 7, 5 => 5 ),
      2 => array ( 0 => 0, 1 => 8, 3 => 3, 4 => 4, 5 => 5 ),
      3 => array ( 1 => 8, 2 => 1, 5 => 5 ),
      4 => array ( 1 => 1, 3 => 3, 6 => 9 ),
      5 => array ( 1 => 8, 4 => 4 ),
      6 => array ( 0 => 0, 2 => 2, 6 => 9 ),
      7 => array ( 1 => 8, 2 => 1 ),
      8 => array ( 0 => 6, 1 => 8, 2 => 7, 3 => 3, 5 => 4 ),
      9 => array ( 0 => 0, 3 => 3, 4 => 9, 5 => 8, 6 => 5 ),
      10 => array ( 1 => 8 ),
      11 => array ( 0 => 6, 2 => 2, 4 => 4, 5 => 5, 6 => 9 ),
      12 => array ( 2 => 1, 3 => 2, 6 => 9 ),
      13 => array ( 0 => 0, 2 => 9, 4 => 7, 5 => 8, 6 => 6 ),
      14 => array ( 0 => 7, 3 => 3, 4 => 9, 5 => 5, 6 => 6 ),
    );

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
     * Jadwal disusun dari JADWAL_GURU_MAP sehingga tidak ada jadwal guru yang bentrok
     * dan setiap guru mengajar maksimal 3 kelas pada tahun ajaran aktif.
     *
     * @param  Collection<int, SchoolClass>  $kelas
     * @param  Collection<int, Subject>  $mapel
     * @param  Collection<int, User>  $guru
     */
    private function buatJadwal(Collection $kelas, Collection $mapel, Collection $guru): void
    {
        foreach ($kelas->values() as $indexKelas => $k) {
            $slots = self::CLASS_SLOTS[$indexKelas];
            foreach ($slots as $idx => $slotIndex) {
                $dayIndex = intdiv($slotIndex, count(self::SLOT)); // 0..4
                $slotInDay = $slotIndex % count(self::SLOT); // 0..2
                $hari = DayOfWeek::cases()[$dayIndex];
                $waktuSlot = self::SLOT[$slotInDay];

                $subjectIndex = self::CLASS_SUBJECTS[$indexKelas][$idx];
                $guruIndex = self::SLOT_ASSIGNMENTS[$slotIndex][$indexKelas];

                Schedule::create([
                    'class_id' => $k->id,
                    'subject_id' => $mapel[$subjectIndex]->id,
                    'teacher_id' => $guru[$guruIndex]->id,
                    'day_of_week' => $hari,
                    'start_time' => $waktuSlot[0],
                    'end_time' => $waktuSlot[1],
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, SchoolClass>  $kelas
     */
    private function buatSiswa(Collection $kelas): void
    {
        $urut = 0;

        foreach ($kelas as $k) {
            for ($i = 1; $i <= self::SISWA_PER_KELAS; $i++) {
                $urut++;
                $namaDepan = self::NAMA_DEPAN[($urut - 1) % count(self::NAMA_DEPAN)];
                $namaBelakang = self::NAMA_BELAKANG[($urut - 1) % count(self::NAMA_BELAKANG)];

                Student::create([
                    'class_id' => $k->id,
                    // Nomor urut global, bukan crc32(): hash bisa bertabrakan
                    // antar kelas dan langsung melanggar unique constraint.
                    'nisn' => (string) (100000 + $urut),
                    'name' => $namaDepan.' '.$namaBelakang,
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
