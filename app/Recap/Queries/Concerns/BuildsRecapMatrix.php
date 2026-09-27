<?php

namespace App\Recap\Queries\Concerns;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait BuildsRecapMatrix
{
    /**
     * @param  Collection<int,SchoolClass>  $classes
     * @param  Collection<int,Schedule>  $schedules
     * @param  Collection<int,Student>|null  $students
     */
    protected function buildResult(
        Collection $classes,
        Collection $schedules,
        RecapPeriod $period,
        ?Collection $students = null,
    ): RecapResult {
        $schedules = $this->filterByDay($schedules, $period);

        $subjects = Subject::query()
            ->whereIn('id', $schedules->pluck('subject_id')->unique())
            ->orderBy('name')
            ->get();

        $siswa = $students
            ? $students->sortBy('name')->values()
            : Student::query()
                ->whereIn('class_id', $classes->pluck('id'))
                ->with('schoolClass')
                ->orderBy('name')
                ->get();

        $jumlah = $this->aggregate($schedules->pluck('id'), $period);

        $summary = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        $rows = [];

        foreach ($siswa as $murid) {
            $cells = [];
            $totalSiswa = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

            foreach ($subjects as $mapel) {
                $jadwalMapel = $schedules
                    ->filter(fn (Schedule $j) => $j->subject_id === $mapel->id && $j->class_id === $murid->class_id);

                $cell = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

                foreach ($jadwalMapel as $jadwal) {
                    foreach ($jumlah["{$murid->id}|{$jadwal->id}"] ?? [] as $status => $banyak) {
                        $cell[$status] += $banyak;
                    }
                }

                $cell['total'] = array_sum($cell);
                $cells[$mapel->id] = $cell;

                foreach (array_keys($summary) as $status) {
                    $totalSiswa[$status] += $cell[$status];
                    $summary[$status] += $cell[$status];
                }
            }

            $totalSiswa['total'] = array_sum($totalSiswa);

            $rows[] = [
                'student' => $murid,
                'class' => $murid->schoolClass,
                'cells' => $cells,
                'total' => $totalSiswa,
            ];
        }

        return new RecapResult(
            classes: $classes->values(),
            period: $period,
            subjects: $subjects,
            rows: $rows,
            summary: $summary,
        );
    }

    /**
     * @param  Collection<int,Schedule>  $schedules
     * @return Collection<int,Schedule>
     */
    private function filterByDay(Collection $schedules, RecapPeriod $period): Collection
    {
        if ($period->type !== 'daily') {
            return $schedules;
        }

        $nomorHari = (int) $period->start->format('N');

        return $schedules
            ->filter(fn (Schedule $j) => $j->day_of_week->value === $nomorHari)
            ->values();
    }

    /**
     * Satu query agregat untuk seluruh matriks, bukan satu query per pasangan
     * siswa-jadwal. `DB::table` dipakai supaya `status` tetap string biasa —
     * kalau lewat Eloquent, nilainya jadi enum dan key array tidak bisa dipakai
     * langsung sebagai index.
     *
     * @param  Collection<int,string>  $scheduleIds
     * @return array<string,array<string,int>>
     */
    private function aggregate(Collection $scheduleIds, RecapPeriod $period): array
    {
        if ($scheduleIds->isEmpty()) {
            return [];
        }

        $baris = DB::table('attendances')
            ->selectRaw('student_id, schedule_id, status, COUNT(*) as jml')
            ->whereIn('schedule_id', $scheduleIds)
            ->whereBetween('date', [
                $period->start->toDateString(),
                $period->end->toDateString(),
            ])
            ->groupBy('student_id', 'schedule_id', 'status')
            ->get();

        $hasil = [];

        foreach ($baris as $row) {
            $hasil["{$row->student_id}|{$row->schedule_id}"][$row->status] = (int) $row->jml;
        }

        return $hasil;
    }
}
