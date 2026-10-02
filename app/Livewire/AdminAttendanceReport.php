<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapExporter;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan absensi lintas kelas. Satu-satunya role yang boleh membukanya
 * adalah admin, jadi ability `viewAnyRecap` diperiksa di `mount()`.
 *
 * Gate dipasang di `mount()`, bukan hanya dengan menyembunyikan tab, karena
 * tab bisa disembunyikan di satu tempat sementara rutenya tetap terbuka.
 * Query-nya memang tidak butuh actor - semua kelas memang yang ditanyakan -
 * jadi `mount()` adalah pagar aksesnya, bukan sekadar penata tampilan.
 *
 * Halaman ini full-page component, jadi tidak memakai shell tab
 * `recap.index` seperti dua halaman rekap sebelumnya.
 */
#[Layout('layouts.app')]
class AdminAttendanceReport extends Component
{
    use InteractsWithRecapPeriod;

    /**
     * @return array<string,list<string>>
     */
    protected function rules(): array
    {
        return $this->recapRules();
    }

    public function mount(): void
    {
        Gate::authorize('viewAnyRecap');

        $this->initializeRecap();
    }

    public function render(): View
    {
        $tahun = $this->year();
        $periode = $this->period($tahun);

        return view('livewire.admin-attendance-report', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'result' => $this->rekap($tahun, $periode),
        ]);
    }

    /**
     * Angka yang diekspor dibangun ulang lewat query yang sama persis dengan
     * `render()`, jadi isi file Excel dan PDF dijamin sama dengan yang tampil di
     * layar.
     *
     * `RecapExporter` mengembalikan string byte, bukan file yang ditulis ke disk,
     * jadi string itu langsung dialirkan sebagai respons download. Jangan tulis ke
     * `storage/app`: kuota disk di shared hosting sering mepet.
     */
    public function exportExcel(): StreamedResponse
    {
        $tahun = $this->year();
        $hasil = $this->hasilEkspor($tahun);

        $exporter = app(RecapExporter::class);
        $excel = $exporter->toExcel($hasil, $tahun);

        return response()->streamDownload(
            static fn () => print ($excel),
            $exporter->excelFilename($hasil),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function exportPdf(): StreamedResponse
    {
        $tahun = $this->year();
        $hasil = $this->hasilEkspor($tahun);

        $exporter = app(RecapExporter::class);
        $pdf = $exporter->toPdf($hasil, $tahun);

        return response()->streamDownload(
            static fn () => print ($pdf),
            $exporter->pdfFilename($hasil),
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function hasilEkspor(?AcademicYear $tahun): RecapResult
    {
        $hasil = $this->rekap($tahun, $this->period($tahun));

        if ($hasil === null) {
            abort(422, 'Periode tidak valid, tidak ada data yang bisa diekspor.');
        }

        return $hasil;
    }

    /**
     * Periode di luar tahun ajaran menghasilkan null, bukan hasil kosong.
     * "Tidak ada data" dan "periode salah" harus dibedakan supaya admin tidak
     * mengunduh tabel kosong yang terlihat sahih.
     */
    private function rekap(?AcademicYear $tahun, ?RecapPeriod $periode): ?RecapResult
    {
        if ($tahun === null || $periode === null) {
            return null;
        }

        return app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->run($periode);
    }
}
