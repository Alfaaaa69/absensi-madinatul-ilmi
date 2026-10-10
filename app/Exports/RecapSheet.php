<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
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
        // Batas data ditulis di dalam sel Periode, bukan sebagai baris baru:
        // `BARIS_META` dipakai untuk menebalkan baris header, dan menambah
        // baris di sini akan menggeser posisi itu.
        $periode = $this->result->period->label();
        $catatanBatas = $this->result->period->countedThroughNote();

        if ($catatanBatas !== null) {
            $periode .= ' ('.$catatanBatas.')';
        }

        $baris = [
            ['LAPORAN KEHADIRAN SISWA'],
            ['Tahun Ajaran', $this->tahun->name ?? '-'],
            ['Periode', $periode],
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

        // Baris ini ditambahkan setelah header, bukan di area metadata, supaya
        // posisi `BARIS_META` tidak bergeser.
        if (! $this->result->hasData()) {
            $baris[] = ['', '', '', '', 'Tidak ada data absensi pada periode ini.'];
        }

        $baris[] = [''];
        $baris[] = ['RINGKASAN', '', '', ''];

        foreach (AttendanceStatus::cases() as $status) {
            $baris[] = [$status->label(), $this->result->summary[$status->value]];
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

        return array_merge($header, array_map(
            static fn (AttendanceStatus $status): string => $status->label(),
            AttendanceStatus::cases(),
        ));
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
