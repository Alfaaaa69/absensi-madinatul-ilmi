<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriod;
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

class SchoolTimetableSeeder extends Seeder
{
    private const GURU_LIST = [
        ['name' => 'Siti Nur Sofiah, S.Pd.', 'nuptk' => '201000000000000001', 'mapel' => ['Matematika']],
        ['name' => 'Samsul Bahri Mohammad, M.Pd.', 'nuptk' => '201000000000000002', 'mapel' => ['IPS', 'Seni Budaya']],
        ['name' => 'Drs. H. Ahmad Fauzi', 'nuptk' => '201000000000000003', 'mapel' => ['Pendidikan Agama Islam', 'Akidah']],
        ['name' => 'Nurul Hidayah, S.Pd.', 'nuptk' => '201000000000000004', 'mapel' => ['Bahasa Indonesia']],
        ['name' => 'Budi Santoso, M.Pd.', 'nuptk' => '201000000000000005', 'mapel' => ['Bahasa Inggris']],
        ['name' => 'Dr. Hj. Siti Aminah', 'nuptk' => '201000000000000006', 'mapel' => ['Ilmu Pengetahuan Alam']],
        ['name' => 'Eko Prasetyo, S.Pd.', 'nuptk' => '201000000000000007', 'mapel' => ['Pancasila dan Kewarganegaraan']],
        ['name' => 'Dewi Lestari, S.Pd.', 'nuptk' => '201000000000000008', 'mapel' => ['Bahasa Arab']],
        ['name' => 'Ahmad Jaelani, S.Pd.', 'nuptk' => '201000000000000009', 'mapel' => ['PJOK']],
        ['name' => 'Rina Marlina, S.Pd.', 'nuptk' => '201000000000000010', 'mapel' => ['Matematika', 'Ilmu Pengetahuan Alam']],
    ];

    private const MAPEL_LIST = [
        'Matematika' => 'MTK',
        'Ilmu Pengetahuan Alam' => 'IPA',
        'IPS' => 'IPS',
        'Bahasa Indonesia' => 'BINDO',
        'Bahasa Inggris' => 'BING',
        'Pendidikan Agama Islam' => 'PAI',
        'Pancasila dan Kewarganegaraan' => 'PPKN',
        'Seni Budaya' => 'SBK',
        'PJOK' => 'PJOK',
        'Akidah' => 'AKD',
        'Bahasa Arab' => 'BARAB',
    ];

    // Setiap sesi adalah 1 blok pembelajaran langsung (2 jam pelajaran berturut-turut)
    // Sesi 1: Jam 1-2 (07:30 - 08:50)
    // Sesi 2: Jam 3-4 (08:50 - 10:10)
    // *Istirahat 10:10 - 10:25*
    // Sesi 3: Jam 5-6 (10:25 - 11:45)
    // *Istirahat 11:45 - 12:25*
    // Sesi 4: Jam 7-8 (12:25 - 13:45)
    private const BLOK_SESI = [
        ['07:30:00', '08:50:00'],
        ['08:50:00', '10:10:00'],
        ['10:25:00', '11:45:00'],
        ['12:25:00', '13:45:00'],
    ];

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

        $guruCollection = collect(self::GURU_LIST)->map(fn ($g) => User::create([
            'name' => $g['name'],
            'nuptk' => $g['nuptk'],
            'email' => strtolower(explode(' ', trim(str_replace(['.', ','], '', $g['name'])))[0]).'@sekolah.sch.id',
            'password' => 'password',
            'role' => UserRole::Guru,
            'is_active' => true,
        ]));

        $subjectMap = collect(self::MAPEL_LIST)->map(fn ($kode, $nama) => Subject::create([
            'name' => $nama,
            'code' => $kode,
        ]));

        $rombel = ['A', 'B', 'C'];
        $kelasCollection = collect();
        $assignedWaliCount = 0;

        foreach ([7, 8, 9] as $tingkat) {
            foreach ($rombel as $r) {
                $namaKelas = "{$tingkat}-{$r}";
                // Tepat 1 guru menjadi wali untuk 1 kelas
                $homeroomId = $assignedWaliCount < $guruCollection->count()
                    ? $guruCollection[$assignedWaliCount]->id
                    : null;
                $assignedWaliCount++;

                $kelas = SchoolClass::create([
                    'academic_year_id' => $tahun->id,
                    'homeroom_teacher_id' => $homeroomId,
                    'name' => $namaKelas,
                    'grade_level' => $tingkat,
                ]);
                $kelasCollection->push($kelas);
            }
        }

        // Mapping guru ke mapel
        $guruMapelMap = [
            0 => ['Matematika'],
            1 => ['IPS', 'Seni Budaya'],
            2 => ['Pendidikan Agama Islam', 'Akidah'],
            3 => ['Bahasa Indonesia'],
            4 => ['Bahasa Inggris'],
            5 => ['Ilmu Pengetahuan Alam'],
            6 => ['Pancasila dan Kewarganegaraan'],
            7 => ['Bahasa Arab'],
            8 => ['PJOK'],
            9 => ['Matematika', 'Ilmu Pengetahuan Alam'],
        ];

        // 1 Guru mengajar maksimal 3 kelas
        $teacherClassesMap = [
            0 => ['7-A', '8-A', '9-A'],
            1 => ['7-A', '8-A', '9-B'],
            2 => ['7-B', '8-B', '9-A'],
            3 => ['7-A', '8-B', '9-C'],
            4 => ['7-B', '8-C', '9-A'],
            5 => ['7-C', '8-A', '9-B'],
            6 => ['7-B', '8-C', '9-C'],
            7 => ['7-C', '8-B', '9-A'],
            8 => ['7-C', '8-C', '9-B'],
            9 => ['7-B', '8-B', '9-C'],
        ];

        // Mapel besar dipecah jadi 2 sesi di 2 hari berbeda
        // Mapel kecil cukup 1 sesi di 1 hari saja
        $mapelBesar = ['Matematika', 'Bahasa Indonesia', 'Ilmu Pengetahuan Alam', 'Bahasa Inggris'];

        $kelasByName = $kelasCollection->keyBy('name');
        $hariList = [DayOfWeek::Senin, DayOfWeek::Selasa, DayOfWeek::Rabu, DayOfWeek::Kamis, DayOfWeek::Jumat];

        $guruTerpakai = [];
        $kelasTerpakai = [];

        foreach ($teacherClassesMap as $gIdx => $classNames) {
            $guru = $guruCollection[$gIdx];
            $mapelNames = $guruMapelMap[$gIdx];

            foreach ($classNames as $cName) {
                $kelas = $kelasByName->get($cName);
                if (! $kelas) continue;

                foreach ($mapelNames as $mName) {
                    $mapel = $subjectMap->get($mName);
                    if (! $mapel) continue;

                    $isBesar = in_array($mName, $mapelBesar, true);
                    $targetSesi = $isBesar ? 2 : 1;
                    $sesiBerhasil = 0;
                    $hariSudahTerpakaiMapelIni = [];

                    foreach ($hariList as $hari) {
                        if ($sesiBerhasil >= $targetSesi) break;
                        if (in_array($hari->value, $hariSudahTerpakaiMapelIni, true)) continue;

                        // Cari 1 sesi blok yang kosong untuk guru dan kelas
                        foreach (self::BLOK_SESI as $slotIdx => $waktu) {
                            $keyTime = "{$hari->value}|{$slotIdx}";

                            $guruSibuk = isset($guruTerpakai[$keyTime][$guru->id]);
                            $kelasSibuk = isset($kelasTerpakai[$keyTime][$kelas->id]);

                            if (! $guruSibuk && ! $kelasSibuk) {
                                Schedule::create([
                                    'class_id' => $kelas->id,
                                    'subject_id' => $mapel->id,
                                    'teacher_id' => $guru->id,
                                    'day_of_week' => $hari,
                                    'start_time' => $waktu[0],
                                    'end_time' => $waktu[1],
                                ]);

                                $guruTerpakai[$keyTime][$guru->id] = true;
                                $kelasTerpakai[$keyTime][$kelas->id] = true;
                                $hariSudahTerpakaiMapelIni[] = $hari->value;
                                $sesiBerhasil++;
                                break; // Selesai 1 sesi di hari ini, lanjut ke hari lain jika target > 1
                            }
                        }
                    }
                }
            }
        }

        // Buat Siswa (30 siswa per kelas)
        $urut = 0;
        $namaDepan = ['Ahmad', 'Muhammad', 'Mohamad', 'Agus', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gilang', 'Hana', 'Indra', 'Jihan', 'Kurnia', 'Lina', 'Mita', 'Nur', 'Oki', 'Putra', 'Rudi', 'Santi', 'Toni', 'Udin', 'Wahyu', 'Xavier', 'Yudi', 'Zaki', 'Ade', 'Bayu', 'Cakra'];
        $namaBelakang = ['Saputra', 'Sutrisno', 'Wijaya', 'Santoso', 'Pratama', 'Nugraha', 'Setiawan', 'Kusuma', 'Maulana', 'Hidayat', 'Permana', 'Gunawan', 'Wibowo', 'Saputro', 'Pamungkas', 'Susanto', 'Ferdiansyah', 'Sulistyo', 'Nuryanto', 'Kuswandari', 'Suryani', 'Lestari', 'Anggraini', 'Dewanti', 'Rahmawati', 'Sulistyorini', 'Puspasari', 'Mulyani', 'Setyawati', 'Wulandari'];

        foreach ($kelasCollection as $k) {
            for ($i = 1; $i <= 30; $i++) {
                $urut++;
                $nd = $namaDepan[($urut - 1) % count($namaDepan)];
                $nb = $namaBelakang[($urut - 1) % count($namaBelakang)];
                Student::create([
                    'class_id' => $k->id,
                    'nisn' => (string) (200000 + $urut),
                    'name' => $nd.' '.$nb,
                    'gender' => $i % 2 === 0 ? StudentGender::P : StudentGender::L,
                ]);
            }
        }
    }

    private function buatTahunAjaran(): AcademicYear
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        return AcademicYear::create([
            'name' => $tahunMulai.'/'.($tahunMulai + 1),
            'period' => AcademicPeriod::Ganjil,
            'start_date' => sprintf('%d-07-01', $tahunMulai),
            'end_date' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_active' => true,
        ]);
    }
}
