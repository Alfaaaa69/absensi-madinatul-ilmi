<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Jadwal Senin kelas 7C-9C dengan struktur waktu yang sama seperti kelas A/B.
 * Jam 0 (Devotion) bukan mata pelajaran, jadi tidak dibuatkan baris.
 *
 * Komposisi: mapel inti 2 jam, mapel pendukung 1 jam. Guru dipilih dari guru
 * yang sudah mengajar mapel tersebut dan yang kosong pada jam itu.
 */
class MondayScheduleCSeeder extends Seeder
{
    private const KELAS = ['7-C', '8-C', '9-C'];

    /** Nama mapel di tabel -> nama subject yang sudah ada. */
    private const MAPEL = [
        'MTK' => 'Matematika',
        'IPA' => 'Ilmu Pengetahuan Alam',
        'B. Indonesia' => 'Bahasa Indonesia',
        'B. Inggris' => 'Bahasa Inggris',
        'PPKN' => 'Pancasila dan Kewarganegaraan',
        'PAI' => 'Pendidikan Agama Islam',
        'PJOK' => 'PJOK',
        'B. Arab' => 'Bahasa Arab',
    ];

    /** [jam mulai, jam selesai, kelas => [mapel, guru]] */
    private const JADWAL = [
        ['07:45:00', '08:20:00', [
            '7-C' => ['B. Inggris', 'Budi Santoso, M.Pd.'],
            '8-C' => ['MTK', 'Siti Nur Sofiah, S.Pd.'],
            '9-C' => ['MTK', 'Rina Marlina, S.Pd.'],
        ]],
        ['08:20:00', '08:55:00', [
            '7-C' => ['MTK', 'Siti Nur Sofiah, S.Pd.'],
            '8-C' => ['B. Inggris', 'Budi Santoso, M.Pd.'],
            '9-C' => ['MTK', 'Rina Marlina, S.Pd.'],
        ]],
        ['08:55:00', '09:30:00', [
            '7-C' => ['PPKN', 'Eko Prasetyo, S.Pd.'],
            '8-C' => ['MTK', 'Siti Nur Sofiah, S.Pd.'],
            '9-C' => ['B. Inggris', 'Budi Santoso, M.Pd.'],
        ]],
        ['09:30:00', '10:05:00', [
            '7-C' => ['B. Indonesia', 'Nurul Hidayah, S.Pd.'],
            '8-C' => ['PPKN', 'Eko Prasetyo, S.Pd.'],
            '9-C' => ['IPA', 'Dr. Hj. Siti Aminah'],
        ]],
        ['10:35:00', '11:10:00', [
            '7-C' => ['IPA', 'Dr. Hj. Siti Aminah'],
            '8-C' => ['B. Indonesia', 'Nurul Hidayah, S.Pd.'],
            '9-C' => ['PPKN', 'Eko Prasetyo, S.Pd.'],
        ]],
        ['11:10:00', '11:45:00', [
            '7-C' => ['MTK', 'Siti Nur Sofiah, S.Pd.'],
            '8-C' => ['IPA', 'Dr. Hj. Siti Aminah'],
            '9-C' => ['PAI', 'Drs. H. Ahmad Fauzi'],
        ]],
        ['11:45:00', '12:20:00', [
            '7-C' => ['B. Arab', 'Dewi Lestari, S.Pd.'],
            '8-C' => ['PJOK', 'Ahmad Jaelani, S.Pd.'],
            '9-C' => ['B. Indonesia', 'Nurul Hidayah, S.Pd.'],
        ]],
        ['12:50:00', '13:25:00', [
            '7-C' => ['IPA', 'Rina Marlina, S.Pd.'],
            '8-C' => ['B. Indonesia', 'Nurul Hidayah, S.Pd.'],
            '9-C' => ['PJOK', 'Ahmad Jaelani, S.Pd.'],
        ]],
        ['13:25:00', '14:00:00', [
            '7-C' => ['PJOK', 'Ahmad Jaelani, S.Pd.'],
            '8-C' => ['IPA', 'Dr. Hj. Siti Aminah'],
            '9-C' => ['IPA', 'Rina Marlina, S.Pd.'],
        ]],
    ];

    public function run(): void
    {
        $classes = SchoolClass::query()->whereIn('name', self::KELAS)->get()->keyBy('name');
        $subjects = Subject::query()->whereIn('name', array_values(self::MAPEL))->get()->keyBy('name');

        $this->assertMasterDataLengkap($classes, $subjects);

        Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->whereIn('class_id', $classes->pluck('id'))
            ->delete();

        foreach (self::JADWAL as [$start, $end, $rows]) {
            foreach ($rows as $className => [$alias, $teacherName]) {
                $class = $classes->get($className);
                $subject = $subjects->get(self::MAPEL[$alias]);
                $teacher = User::query()
                    ->where('name', $teacherName)
                    ->where('role', UserRole::Guru)
                    ->firstOrFail();

                Schedule::create([
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'day_of_week' => DayOfWeek::Senin,
                    'start_time' => $start,
                    'end_time' => $end,
                ]);
            }
        }
    }

    /**
     * @param  Collection<string, SchoolClass>  $classes
     * @param  Collection<string, Subject>  $subjects
     */
    private function assertMasterDataLengkap(Collection $classes, Collection $subjects): void
    {
        $missingClasses = array_diff(self::KELAS, $classes->keys()->all());
        $missingSubjects = array_diff(array_values(self::MAPEL), $subjects->keys()->all());

        if ($missingClasses !== [] || $missingSubjects !== []) {
            throw new \RuntimeException(
                'Master data belum lengkap. Kelas: '.implode(', ', $missingClasses).
                '. Mapel: '.implode(', ', $missingSubjects)
            );
        }
    }
}
