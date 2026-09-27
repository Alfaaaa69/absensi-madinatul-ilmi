<?php

namespace App\Recap;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @implements Arrayable<string, mixed>
 */
final class RecapResult implements Arrayable
{
    /** @var Collection<int,array{student: Student, class: SchoolClass, cells: array<string,non-empty-array<string,int>>, total: array{hadir: int, izin: int, sakit: int, alpa: int, total: int}}> */
    public readonly Collection $rows;

    /**
     * `rows` diterima sebagai array, bukan Collection: baris matriks selalu
     * dibangun sebagai array lalu dibungkus sekali di sini, dan tipe array
     * bersifat kovarian sehingga tidak bentrok dengan invariansi generic.
     *
     * @param  Collection<int,SchoolClass>  $classes
     * @param  Collection<int,Subject>  $subjects
     * @param  array<int,array{student: Student, class: SchoolClass, cells: array<string,non-empty-array<string,int>>, total: array{hadir: int, izin: int, sakit: int, alpa: int, total: int}}>  $rows
     * @param  array<string,int>  $summary
     */
    public function __construct(
        public readonly Collection $classes,
        public readonly RecapPeriod $period,
        public readonly Collection $subjects,
        array $rows,
        public readonly array $summary,
    ) {
        $this->rows = collect($rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'classes' => $this->classes->toArray(),
            'period' => [
                'type' => $this->period->type,
                'start' => $this->period->start->toDateString(),
                'end' => $this->period->end->toDateString(),
                'label' => $this->period->label(),
            ],
            'subjects' => $this->subjects->toArray(),
            'rows' => $this->rows->toArray(),
            'summary' => $this->summary,
        ];
    }
}
