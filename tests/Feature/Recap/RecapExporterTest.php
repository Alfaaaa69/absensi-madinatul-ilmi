<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapExporter;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

class RecapExporterTest extends TestCase
{
    use RefreshDatabase;

    private function hasil(): RecapResult
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        Schedule::factory()->create(['class_id' => $kelas->id]);
        Student::factory()->create(['class_id' => $kelas->id]);

        return app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelas)
            ->run(RecapPeriod::monthly(2025, 9));
    }

    public function test_menghasilkan_file_excel_xlsx(): void
    {
        $excel = app(RecapExporter::class)->toExcel($this->hasil());

        $this->assertNotSame('', $excel);
        $this->assertSame('PK', substr($excel, 0, 2), 'XLSX adalah file ZIP, harus diawali PK');

        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($path, $excel);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();

        unlink($path);
    }

    public function test_menghasilkan_file_pdf(): void
    {
        $hasil = $this->hasil();

        $this->assertStringStartsWith('%PDF', app(RecapExporter::class)->toPdf($hasil));
    }

    public function test_view_pdf_menampilkan_data_dari_recap_result(): void
    {
        $hasil = $this->hasil();

        $html = view('recap.print', ['result' => $hasil, 'tahun' => null])->render();

        // Isi PDF terkompresi (FlateDecode) jadi tidak bisa dicari di byte
        // mentah. Yang diuji di sini adalah view-nya: sumber angka yang sama
        // dengan yang dibaca exporter.
        $this->assertStringContainsString($hasil->rows->first()['student']->name, $html);
        $this->assertStringContainsString($hasil->rows->first()['student']->nisn, $html);
        $this->assertStringContainsString($hasil->subjects->first()->name, $html);
        $this->assertStringContainsString($hasil->period->label(), $html);
    }

    /**
     * Rentang di nama berkas adalah rentang yang benar-benar dihitung, bukan
     * periode penuh. Kalau memakai `$period->end`, laporan September yang diunduh
     * tanggal 15 dan tanggal 30 punya nama sama tapi isi berbeda - yang kedua
     * menimpa yang pertama tanpa jejak.
     */
    public function test_nama_berkas_memuat_rentang_yang_dihitung(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));

        $hasil = $this->hasil();
        $exporter = app(RecapExporter::class);

        $this->assertSame('2025-09-15', $hasil->period->countedEnd()?->toDateString());

        $this->assertSame('rekap-absensi-2025-09-01-2025-09-15.xlsx', $exporter->excelFilename($hasil));
        $this->assertSame('rekap-absensi-2025-09-01-2025-09-15.pdf', $exporter->pdfFilename($hasil));
    }

    public function test_nama_berkas_berbeda_untuk_dua_snapshot_periode_yang_sama(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));

        $hasil = $this->hasil();
        $exporter = app(RecapExporter::class);

        $pertama = $exporter->excelFilename($hasil);

        Carbon::setTestNow(Carbon::parse('2025-09-20'));

        $kedua = $exporter->excelFilename($hasil);

        $this->assertNotSame($pertama, $kedua, 'Snapshot berbeda harus punya nama berkas berbeda.');
        $this->assertSame('rekap-absensi-2025-09-01-2025-09-20.xlsx', $kedua);
    }

    public function test_nama_berkas_memakai_rentang_penuh_saat_periode_sudah_selesai(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-10-01'));

        $hasil = $this->hasil();

        $this->assertSame('rekap-absensi-2025-09-01-2025-09-30.xlsx', app(RecapExporter::class)->excelFilename($hasil));
    }

    /**
     * Prefiks berasal dari `.env`, jadi bisa berisi apa saja yang diketik admin.
     * Nilai kosong atau berisi `/` tidak boleh sampai jadi nama berkas.
     */
    public function test_prefiks_berkarakter_aneh_dibersihkan(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-15'));

        $hasil = $this->hasil();
        $exporter = app(RecapExporter::class);

        config(['rekap.file_prefix' => 'laporan absensi/../../etc']);
        $this->assertSame('laporan-absensi-etc-2025-09-01-2025-09-15.xlsx', $exporter->excelFilename($hasil));

        config(['rekap.file_prefix' => '  ']);
        $this->assertSame('rekap-absensi-2025-09-01-2025-09-15.xlsx', $exporter->excelFilename($hasil));
    }
}
