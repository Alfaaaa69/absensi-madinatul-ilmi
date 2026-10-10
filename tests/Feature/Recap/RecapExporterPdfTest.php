<?php

namespace Tests\Feature\Recap;

use App\Enums\AttendanceStatus;
use App\Exports\RecapSheet;
use App\Livewire\AdminAttendanceReport;
use App\Models\User;
use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class RecapExporterPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_mengunduh_file(): void
    {
        RecapScenario::waliKelas();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-09')
            ->call('exportPdf')
            ->assertFileDownloaded('rekap-absensi-2025-09-01-2025-09-15.pdf');
    }

    /**
     * Angka yang sama harus muncul di layar dan di PDF. Isi PDF terkompresi
     * jadi tidak bisa dibaca dari byte mentah, jadi layout PDF dirender
     * langsung dan dibandingkan dengan output component yang sama persis.
     */
    public function test_isi_pdf_menyalin_angka_dari_recap_result(): void
    {
        $s = RecapScenario::waliKelas();

        $layar = Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-09')
            ->html();

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($s->tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $pdf = view('recap.print', ['result' => $hasil, 'tahun' => $s->tahun])->render();

        $this->assertStringContainsString('Ahmad Fauzi', $pdf);

        foreach ($hasil->summary as $status => $jumlah) {
            $angka = AttendanceStatus::from($status)->label().' '.$jumlah;

            $this->assertStringContainsString($angka, $pdf, "PDF kehilangan ringkasan: {$angka}");
            $this->assertStringContainsString($angka, $layar, "Layar kehilangan ringkasan: {$angka}");
        }
    }

    public function test_pdf_ditolak_saat_periode_tidak_valid(): void
    {
        RecapScenario::waliKelas();

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-07')
            ->call('exportPdf')
            ->assertStatus(422);
    }

    /**
     * Skenario ini sudah dibakukan di `RecapScenario`: "hari ini" 15 September
     * 2025, jadi rekap September sengaja hanya menghitung sampai tanggal itu.
     * Kalau batasnya tidak disebut di ketiga permukaan, laporan yang diunduh
     * tanggal 15 terlihat sama dengan laporan akhir bulan, padahal angkanya
     * berbeda.
     */
    public function test_batas_data_terlihat_di_layar_pdf_dan_excel(): void
    {
        $s = RecapScenario::waliKelas();

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($s->tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $catatan = $hasil->period->countedThroughNote();

        $this->assertSame('Data per 15 September 2025', $catatan);

        $layar = Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->set('month', '2025-09')
            ->html();

        $this->assertStringContainsString($catatan, $layar, 'Layar tidak menyebut batas data.');

        $pdf = view('recap.print', ['result' => $hasil, 'tahun' => $s->tahun])->render();

        $this->assertStringContainsString($catatan, $pdf, 'PDF tidak menyebut batas data.');

        $excel = RecapSheet::for($hasil, $s->tahun)->array();

        $teksExcel = array_map(
            static fn (array $baris): string => implode(' ', array_filter($baris, is_scalar(...))),
            $excel,
        );

        $this->assertTrue(
            collect($teksExcel)->contains(fn (string $baris): bool => str_contains($baris, $catatan)),
            'Excel tidak menyebut batas data.',
        );
    }

    public function test_periode_lampau_tidak_menulis_catatan_batas(): void
    {
        $s = RecapScenario::waliKelas();

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($s->tahun)
            ->run(RecapPeriod::monthly(2025, 8));

        $this->assertNull($hasil->period->countedThroughNote());

        $pdf = view('recap.print', ['result' => $hasil, 'tahun' => $s->tahun])->render();

        $this->assertStringNotContainsString('Data per', $pdf);
    }
}
