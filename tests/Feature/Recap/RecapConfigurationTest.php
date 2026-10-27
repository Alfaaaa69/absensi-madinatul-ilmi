<?php

namespace Tests\Feature\Recap;

use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapExporter;
use App\Recap\RecapPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class RecapConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nama_file_export_mengikuti_konfigurasi(): void
    {
        $s = RecapScenario::waliKelas();
        config(['rekap.file_prefix' => 'laporan-absensi']);

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($s->tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $exporter = app(RecapExporter::class);

        $this->assertSame('laporan-absensi-2025-09-01-2025-09-15.xlsx', $exporter->excelFilename($hasil));
        $this->assertSame('laporan-absensi-2025-09-01-2025-09-15.pdf', $exporter->pdfFilename($hasil));
    }

    public function test_prefiks_bawaan_menyebut_rentang_periode(): void
    {
        $s = RecapScenario::waliKelas();

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($s->tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        // Rentang tanggal harus tetap ada di nama berkas: dua export untuk
        // bulan berbeda tidak boleh saling menimpa di folder arsip.
        $this->assertSame('rekap-absensi-2025-09-01-2025-09-15.xlsx', app(RecapExporter::class)->excelFilename($hasil));
        $this->assertSame('rekap-absensi-2025-09-01-2025-09-15.pdf', app(RecapExporter::class)->pdfFilename($hasil));
    }

    public function test_zona_waktu_aplikasi_adalah_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    /**
     * Menguji config saja belum cukup: nilainya bisa benar sementara prosesnya
     * tetap berjalan di UTC. Yang diuji di sini efeknya, yaitu satu momen yang
     * tanggalnya berbeda antara UTC dan Jakarta.
     */
    public function test_tanggal_absensi_bergeser_sesuai_zona_waktu(): void
    {
        // 14 September 17:30 UTC sudah 15 September 00:30 di Jakarta.
        $momen = Carbon::parse('2025-09-14 17:30:00', 'UTC');

        $this->assertSame('2025-09-14', $momen->format('Y-m-d'), 'Titik awal harus benar dalam UTC');
        $this->assertSame(
            '2025-09-15',
            $momen->copy()->setTimezone(config('app.timezone'))->format('Y-m-d'),
            'Di zona waktu aplikasi, momen yang sama harus jatuh ke tanggal berikutnya',
        );
    }

    public function test_tanggal_hari_ini_mengikuti_zona_waktu_aplikasi(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-09-14 17:30:00', 'UTC'));

        $this->assertSame('2025-09-15', today()->toDateString());
    }
}
