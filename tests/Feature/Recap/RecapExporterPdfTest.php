<?php

namespace Tests\Feature\Recap;

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
            ->assertFileDownloaded('rekap-absensi-2025-09-01-2025-09-30.pdf');
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
            $angka = ucfirst($status).' '.$jumlah;

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
}
