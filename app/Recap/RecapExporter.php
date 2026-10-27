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
        return $this->stem($result).'.xlsx';
    }

    public function pdfFilename(RecapResult $result): string
    {
        return $this->stem($result).'.pdf';
    }

    /**
     * Nama berkas memakai rentang tanggal yang **benar-benar dihitung**, bukan
     * rentang periode penuh. Kalau pakai `$period->end`, laporan September yang
     * diunduh tanggal 15 dan yang diunduh tanggal 30 punya nama sama tapi isi
     * berbeda, jadi yang kedua menimpa yang pertama tanpa jejak - dan keduanya
     * tetap terbaca sebagai "laporan September".
     */
    private function stem(RecapResult $result): string
    {
        $akhir = $result->period->countedEnd() ?? $result->period->start;

        return $this->prefix().'-'.$result->period->start->format('Y-m-d')
            .'-'.$akhir->format('Y-m-d');
    }

    /**
     * Prefiks berasal dari `.env`, jadi nilainya bisa berisi spasi, `/`, atau
     * karakter aneh dari ketikan admin. Tanpa sanitasi, `REKAP_FILE_PREFIX`
     * berisi spasi menghasilkan nama berkas yang tidak bisa dipesan dengan
     * benar di Windows, dan nilai kosong membuat nama berkas dimulai dengan
     * `-` yang membingungkan.
     *
     * Sanitasi ini menjaga nama berkas tetap lapang. Yang menentukan berkas
     * benar-benar tersimpan di folder unduhan adalah penangan unduhan browser
     * dan konfigurasi web server, bukan nilai ini.
     */
    private function prefix(): string
    {
        $prefiks = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) config('rekap.file_prefix'));
        $prefiks = trim((string) $prefiks, '-');

        return $prefiks === '' ? 'rekap-absensi' : $prefiks;
    }

    private function tahun(?AcademicYear $tahun): ?AcademicYear
    {
        return $tahun ?? AcademicYear::query()->where('is_active', true)->first();
    }
}
