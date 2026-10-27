<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
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
 * Karena itu `exportExcel()` dan `exportPdf()` memeriksa gate yang sama sekali
 * lagi. `mount()` hanya jalan pada request yang merender halaman; tab yang
 * sudah terbuka tidak memanggilnya lagi, jadi admin yang diturunkan jadi guru
 * dari panel user management masih bisa mengunduh laporan seluruh sekolah lewat
 * tab lamanya. Pemeriksaan per-request adalah yang menutup celah itu.
 *
 * Halaman ini full-page component, jadi tidak memakai shell tab
 * `recap.index` seperti dua halaman rekap sebelumnya.
 */
#[Layout('layouts.app')]
class AdminAttendanceReport extends Component
{
    use InteractsWithRecapPeriod;

    /** Kelas yang sedang dibuka; null berarti daftar kartu kelas. */
    public ?string $kelasId = null;

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

    public function pilihKelas(string $kelasId): void
    {
        Gate::authorize('viewAnyRecap');

        $this->kelasId = $kelasId;
    }

    public function kembali(): void
    {
        $this->kelasId = null;
    }

    /**
     * Ganti periode membuat daftar kelas bisa berubah; kembali ke kartu supaya
     * kelas dari tahun ajaran lama tidak tertinggal di layar.
     */
    public function updated(string $property, mixed $value = null): void
    {
        if (in_array($property, ['academicYearId', 'periodType', 'month', 'date'], true)) {
            $this->kelasId = null;
        }
    }

    public function render(): View
    {
        $tahun = $this->year();
        $periode = $this->period($tahun);
        $hasil = $this->rekap($tahun, $periode);

        $kelasTerpilih = null;
        $detail = null;
        $jumlahSiswa = collect();

        if ($hasil !== null) {
            $jumlahSiswa = $hasil->rows->countBy(fn (array $baris) => $baris['class']->id);

            $kelasTerpilih = $this->kelasId === null
                ? null
                : $hasil->classes->firstWhere('id', $this->kelasId);

            if ($this->kelasId !== null && $kelasTerpilih === null) {
                $this->kelasId = null;
            }

            if ($kelasTerpilih !== null) {
                $detail = $this->detailKelas($hasil, $kelasTerpilih);
            }
        }

        return view('livewire.admin-attendance-report', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'result' => $hasil,
            'kelasTerpilih' => $kelasTerpilih,
            'detail' => $detail,
            'jumlahSiswa' => $jumlahSiswa,
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
        Gate::authorize('viewAnyRecap');

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
        Gate::authorize('viewAnyRecap');

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
     * Matriks satu kelas memakai query yang sama; cukup saring baris dan
     * jumlahkan ulang ringkasannya, tanpa query database kedua.
     */
    private function detailKelas(RecapResult $hasil, SchoolClass $kelas): RecapResult
    {
        $rows = $hasil->rows
            ->filter(fn (array $baris) => $baris['class']->id === $kelas->id)
            ->values();

        $summary = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        foreach ($rows as $baris) {
            foreach (array_keys($summary) as $status) {
                $summary[$status] += $baris['total'][$status];
            }
        }

        return new RecapResult(
            classes: collect([$kelas]),
            period: $hasil->period,
            subjects: $hasil->subjects,
            rows: $rows->all(),
            summary: $summary,
        );
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
