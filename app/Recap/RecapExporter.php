<?php

namespace App\Recap;

use App\Exports\RecapSheet;
use App\Models\AcademicYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

final class RecapExporter
{
    public function toExcel(RecapResult $result, ?AcademicYear $tahun = null): string
    {
        return Excel::raw(RecapSheet::for($result, $this->tahun($tahun)), ExcelType::XLSX);
    }

    public function toPdf(RecapResult $result, ?AcademicYear $tahun = null): string
    {
        $pdf = Pdf::loadView('recap.print', [
            'result' => $result,
            'tahun' => $this->tahun($tahun),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isRemoteEnabled', false);

        return $pdf->output();
    }

    public function excelFilename(RecapResult $result): string
    {
        return 'rekap-absensi-'.$result->period->start->format('Y-m-d')
            .'-'.$result->period->end->format('Y-m-d').'.xlsx';
    }

    public function pdfFilename(RecapResult $result): string
    {
        return 'rekap-absensi-'.$result->period->start->format('Y-m-d')
            .'-'.$result->period->end->format('Y-m-d').'.pdf';
    }

    private function tahun(?AcademicYear $tahun): ?AcademicYear
    {
        return $tahun ?? AcademicYear::query()->where('is_active', true)->first();
    }
}
