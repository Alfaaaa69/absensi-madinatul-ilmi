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
 * Jadwal Senin dari docs/jadwal-senin.md untuk kelas 7A-9B.
 *
 * Seeder ini melengkapi mapel yang belum ada di master (Bahasa Jawa, Literasi,
 * BK, Prakarya, Seni & Prakarya, Ekstra. Ketr. Ukir, Ekstra. Science) beserta
 * guru pengampunya, lalu menulis seluruh baris jadwal Senin sesuai sumber.
 *
 * 9B Seni Budaya tetap di jam 7 seperti sumber. Samsul sudah mengajar 8A IPS
 * pada jam itu, jadi 9B Seni Budaya diampu guru Seni & Prakarya.
 */
class MondayScheduleSeeder extends Seeder
{
    private const KELAS = ['7-A', '7-B', '8-A', '8-B', '9-A', '9-B'];

    /** Nama mapel di tabel -> nama subject. */
    private const MAPEL = [
        'IPA' => 'Ilmu Pengetahuan Alam',
        'B. Indonesia' => 'Bahasa Indonesia',
        'PAKBP' => 'Pendidikan Agama Islam',
        'IPS' => 'IPS',
        'MTK' => 'Matematika',
        'B. Inggris' => 'Bahasa Inggris',
        'Pend. Pancasila' => 'Pancasila dan Kewarganegaraan',
        'Seni Budaya' => 'Seni Budaya',
        'B. Jawa' => 'Bahasa Jawa',
        'Literasi' => 'Literasi',
        'BK' => 'Bimbingan Konseling',
        'Prakarya' => 'Prakarya',
        'Seni & Prakarya' => 'Seni & Prakarya',
        'Ekstra. Ketr. Ukir' => 'Ekstra. Ketr. Ukir',
        'Ekstra. Science' => 'Ekstra. Science',
    ];

    /** Mapel yang belum ada di master saat seeder ini dibuat. */
    private const MAPEL_BARU = [
        'Bahasa Jawa' => 'BJW',
        'Literasi' => 'LIT',
        'Bimbingan Konseling' => 'BK',
        'Prakarya' => 'PRK',
        'Seni & Prakarya' => 'SPR',
        'Ekstra. Ketr. Ukir' => 'EKU',
        'Ekstra. Science' => 'ESC',
    ];

    private const GURU_BARU = [
        ['name' => 'Bagus Setyo Nugroho, S.Pd.', 'nuptk' => '201000000000000011', 'email' => 'bagus.setyo@sekolah.sch.id'],
        ['name' => 'Ratna Puspitasari, S.Pd.', 'nuptk' => '201000000000000012', 'email' => 'ratna.puspita@sekolah.sch.id'],
        ['name' => 'Dian Permata Sari, S.Psi.', 'nuptk' => '201000000000000013', 'email' => 'dian.permata@sekolah.sch.id'],
        ['name' => 'Bambang Wijaya, S.Pd.', 'nuptk' => '201000000000000014', 'email' => 'bambang.wijaya@sekolah.sch.id'],
        ['name' => 'Lestari Wulandari, S.Pd.', 'nuptk' => '201000000000000015', 'email' => 'lestari.wulandari@sekolah.sch.id'],
        ['name' => 'Sutrisno Hadi, S.Pd.', 'nuptk' => '201000000000000016', 'email' => 'sutrisno.hadi@sekolah.sch.id'],
        ['name' => 'Fitriani Rahmawati, S.Pd.', 'nuptk' => '201000000000000017', 'email' => 'fitriani.rahma@sekolah.sch.id'],
    ];

    /** Guru untuk mapel baru dan pengecualian yang tidak boleh memakai guru lama. */
    private const GURU_TETAP = [
        '7-A|B. Jawa' => 'Bagus Setyo Nugroho, S.Pd.',
        '9-A|B. Jawa' => 'Bagus Setyo Nugroho, S.Pd.',
        '7-B|Literasi' => 'Ratna Puspitasari, S.Pd.',
        '8-A|BK' => 'Dian Permata Sari, S.Psi.',
        '9-B|BK' => 'Dian Permata Sari, S.Psi.',
        '8-B|Prakarya' => 'Bambang Wijaya, S.Pd.',
        '7-A|Seni & Prakarya' => 'Lestari Wulandari, S.Pd.',
        '9-B|Seni Budaya' => 'Lestari Wulandari, S.Pd.',
        '7-A|Ekstra. Ketr. Ukir' => 'Sutrisno Hadi, S.Pd.',
        '7-B|Ekstra. Ketr. Ukir' => 'Sutrisno Hadi, S.Pd.',
        '8-A|Ekstra. Ketr. Ukir' => 'Sutrisno Hadi, S.Pd.',
        '9-A|Ekstra. Ketr. Ukir' => 'Bambang Wijaya, S.Pd.',
        '9-B|Ekstra. Ketr. Ukir' => 'Bambang Wijaya, S.Pd.',
        '8-A|Ekstra. Science' => 'Fitriani Rahmawati, S.Pd.',
        '8-B|Ekstra. Science' => 'Fitriani Rahmawati, S.Pd.',
    ];

    /** [jam mulai, jam selesai, kelas => mapel] */
    private const JADWAL = [
        ['07:45:00', '08:20:00', [
            '7-A' => 'IPA',
            '7-B' => 'Ekstra. Ketr. Ukir',
            '8-A' => 'Ekstra. Science',
            '8-B' => 'B. Indonesia',
            '9-A' => 'PAKBP',
            '9-B' => 'IPS',
        ]],
        ['08:20:00', '08:55:00', [
            '7-A' => 'IPA',
            '7-B' => 'B. Indonesia',
            '8-A' => 'Ekstra. Ketr. Ukir',
            '8-B' => 'Ekstra. Science',
            '9-A' => 'PAKBP',
            '9-B' => 'IPS',
        ]],
        ['08:55:00', '09:30:00', [
            '7-A' => 'Ekstra. Ketr. Ukir',
            '7-B' => 'B. Indonesia',
            '8-A' => 'IPA',
            '8-B' => 'MTK',
            '9-A' => 'IPS',
            '9-B' => 'BK',
        ]],
        ['09:30:00', '10:05:00', [
            '7-A' => 'B. Jawa',
            '7-B' => 'IPA',
            '8-A' => 'Seni Budaya',
            '8-B' => 'Prakarya',
            '9-A' => 'MTK',
            '9-B' => 'B. Inggris',
        ]],
        ['10:35:00', '11:10:00', [
            '7-A' => 'B. Jawa',
            '7-B' => 'Literasi',
            '8-A' => 'Seni Budaya',
            '8-B' => 'Prakarya',
            '9-A' => 'MTK',
            '9-B' => 'B. Inggris',
        ]],
        ['11:10:00', '11:45:00', [
            '7-A' => 'Pend. Pancasila',
            '7-B' => 'MTK',
            '8-A' => 'BK',
            '8-B' => 'B. Inggris',
            '9-A' => 'B. Indonesia',
            '9-B' => 'Ekstra. Ketr. Ukir',
        ]],
        ['11:45:00', '12:20:00', [
            '7-A' => 'Pend. Pancasila',
            '7-B' => 'MTK',
            '8-A' => 'IPS',
            '8-B' => 'B. Inggris',
            '9-A' => 'B. Jawa',
            '9-B' => 'Seni Budaya',
        ]],
        ['12:50:00', '13:25:00', [
            '7-A' => 'Seni & Prakarya',
            '7-B' => 'Pend. Pancasila',
            '8-A' => 'B. Inggris',
            '8-B' => 'IPS',
            '9-A' => 'B. Jawa',
            '9-B' => 'MTK',
        ]],
        ['13:25:00', '14:00:00', [
            '7-A' => 'Seni & Prakarya',
            '7-B' => 'Pend. Pancasila',
            '8-A' => 'B. Inggris',
            '8-B' => 'IPS',
            '9-A' => 'Ekstra. Ketr. Ukir',
            '9-B' => 'MTK',
        ]],
    ];

    public function run(): void
    {
        $this->pastikanMasterData();

        $classes = SchoolClass::query()->whereIn('name', self::KELAS)->get()->keyBy('name');
        $subjects = Subject::query()->whereIn('name', array_values(self::MAPEL))->get()->keyBy('name');

        $this->assertMasterDataLengkap($classes, $subjects);

        // Guru lama diambil dari jadwal yang sudah ada; mapel baru memakai
        // guru tetap di GURU_TETAP.
        $teachers = $this->teacherMap($classes, $subjects);
        $teachers = $this->terapkanGuruTetap($teachers, $classes, $subjects);

        Schedule::query()
            ->where('day_of_week', DayOfWeek::Senin)
            ->whereIn('class_id', $classes->pluck('id'))
            ->delete();

        foreach (self::JADWAL as [$start, $end, $rows]) {
            foreach ($rows as $className => $alias) {
                $class = $classes->get($className);
                $subject = $subjects->get(self::MAPEL[$alias]);
                $teacherId = $teachers->get($class->id.'|'.$subject->id);

                if (! $teacherId) {
                    throw new \RuntimeException("Tidak ada guru yang mengampu {$subject->name}.");
                }

                Schedule::create([
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacherId,
                    'day_of_week' => DayOfWeek::Senin,
                    'start_time' => $start,
                    'end_time' => $end,
                ]);
            }
        }
    }

    private function pastikanMasterData(): void
    {
        foreach (self::MAPEL_BARU as $name => $code) {
            $subject = Subject::withTrashed()->firstOrCreate(['name' => $name], ['code' => $code]);

            if ($subject->trashed()) {
                $subject->restore();
            }
        }

        foreach (self::GURU_BARU as $data) {
            $guru = User::withTrashed()->firstOrNew(['nuptk' => $data['nuptk']]);
            $baru = ! $guru->exists;

            $guru->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => UserRole::Guru,
                'is_active' => true,
            ]);

            if ($baru) {
                $guru->password = 'password';
            }

            $guru->save();

            if ($guru->trashed()) {
                $guru->restore();
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

    /**
     * @param  Collection<string, SchoolClass>  $classes
     * @param  Collection<string, Subject>  $subjects
     * @return Collection<string, string>
     */
    private function teacherMap(Collection $classes, Collection $subjects): Collection
    {
        $map = collect();

        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                $teacherId = Schedule::query()
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->orderBy('created_at')
                    ->value('teacher_id')
                    ?? Schedule::query()
                        ->where('subject_id', $subject->id)
                        ->orderBy('created_at')
                        ->value('teacher_id');

                $map->put($class->id.'|'.$subject->id, $teacherId);
            }
        }

        return $map;
    }

    /**
     * @param  Collection<string, string>  $teachers
     * @param  Collection<string, SchoolClass>  $classes
     * @param  Collection<string, Subject>  $subjects
     * @return Collection<string, string>
     */
    private function terapkanGuruTetap(Collection $teachers, Collection $classes, Collection $subjects): Collection
    {
        $guru = User::query()
            ->whereIn('name', array_values(self::GURU_TETAP))
            ->get()
            ->keyBy('name');

        foreach (self::GURU_TETAP as $key => $teacherName) {
            [$className, $alias] = explode('|', $key);
            $class = $classes->get($className);
            $subject = $subjects->get(self::MAPEL[$alias]);

            $teachers->put($class->id.'|'.$subject->id, $guru->get($teacherName)->id);
        }

        return $teachers;
    }
}
