<?php

namespace App\Exports;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\RecapResult;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecapSheet implements FromArray, WithStyles, WithTitle
{
    private const BARIS_META = 5;

    public function __construct(
        private readonly RecapResult $result,
        private readonly ?AcademicYear $tahun,
    ) {}

    public static function for(RecapResult $result, ?AcademicYear $tahun): self
    {
        return new self($result, $tahun);
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    /**
     * @return array<int,array<int,int|string|null>>
     */
    public function array(): array
    {
        $baris = [
            ['LAPORAN KEHADIRAN SISWA'],
            ['Tahun Ajaran', $this->tahun->name ?? '-'],
            ['Periode', $this->result->period->label()],
            ['Kelas', $this->result->classes->pluck('name')->implode(', ') ?: '-'],
            [''],
        ];

        $baris[] = $this->header();

        foreach ($this->result->rows as $index => $row) {
            $baris[] = array_merge(
                [
                    $index + 1,
                    $row['student']->nisn,
                    $row['student']->name,
                    $row['class']->name,
                ],
                $this->flattenCells($row),
                [
                    $row['total']['hadir'],
                    $row['total']['izin'],
                    $row['total']['sakit'],
                    $row['total']['alpa'],
                ],
            );
        }

        $baris[] = [''];
        $baris[] = ['RINGKASAN', '', '', ''];

        foreach (['hadir', 'izin', 'sakit', 'alpa'] as $status) {
            $baris[] = [ucfirst($status), $this->result->summary[$status]];
        }

        return $baris;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            self::BARIS_META + 1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function header(): array
    {
        $header = ['No', 'NISN', 'Nama Siswa', 'Kelas'];

        foreach ($this->result->subjects as $mapel) {
            $header[] = $mapel->name;
        }

        return array_merge($header, ['Hadir', 'Izin', 'Sakit', 'Alpa']);
    }

    /**
     * @param  array{student: Student, class: SchoolClass, cells: array<string,non-empty-array<string,int>>, total: array{hadir: int, izin: int, sakit: int, alpa: int, total: int}}  $row
     * @return array<int,string>
     */
    private function flattenCells(array $row): array
    {
        $nilai = [];

        foreach ($this->result->subjects as $mapel) {
            $cell = $row['cells'][$mapel->id] ?? null;

            $nilai[] = $cell !== null && $cell['total'] > 0
                ? "{$cell['hadir']}/{$cell['total']}"
                : '-';
        }

        return $nilai;
    }
}
