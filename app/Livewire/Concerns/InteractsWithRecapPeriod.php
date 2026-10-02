<?php

namespace App\Livewire\Concerns;

use App\Models\AcademicYear;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * State & validasi periode yang dipakai bersama oleh ketiga halaman rekap.
 *
 * `RecapPeriodOutOfRange` adalah kasus input normal — pengguna salah pilih bulan —
 * jadi exception itu ditangkap dan jadi pesan, bukan halaman 500.
 */
trait InteractsWithRecapPeriod
{
    public string $academicYearId = '';

    public string $periodType = 'monthly';

    public string $month = '';

    public string $date = '';

    public ?string $periodError = null;

    protected function initializeRecap(): void
    {
        $tahun = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();

        $this->academicYearId = $tahun !== null ? (string) $tahun->id : '';
        $this->month = $this->defaultMonth($tahun);
        $this->date = now()->toDateString();
    }

    /**
     * Bulan bawaan harus berada di dalam tahun ajaran yang dipilih.
     *
     * Tanpa penjepitan ini, sekolah yang tahun ajarannya mulai bulan Agustus
     * akan membuka halaman rekap pada 1 Juli dan langsung menampilkan pesan
     * "Juli berada di luar tahun ajaran". Aturan "pilih Juli harus error" itu
     * untuk pilihan pengguna; nilai bawaan yang memang tidak mungkin dipakai
     * hanya membuat setiap pengguna Juli melihat halaman error sebagai
     * keadaan pertama.
     */
    private function defaultMonth(?AcademicYear $tahun): string
    {
        $bulanIni = now()->copy();

        if ($tahun === null) {
            return $bulanIni->format('Y-m');
        }

        $awalTahun = $tahun->start_date->copy()->startOfMonth();
        $akhirTahun = $tahun->end_date->copy()->endOfMonth();

        if ($bulanIni->lessThan($awalTahun)) {
            return $awalTahun->format('Y-m');
        }

        return $bulanIni->greaterThan($akhirTahun) ? $akhirTahun->format('Y-m') : $bulanIni->format('Y-m');
    }

    /**
     * @return array<string,list<string>>
     */
    protected function recapRules(): array
    {
        return [
            'academicYearId' => ['required', 'uuid', 'exists:academic_years,id'],
            'periodType' => ['required', 'in:daily,monthly'],
            'month' => ['required', 'format:Y-m'],
            'date' => ['required', 'format:Y-m-d'],
        ];
    }

    /**
     * @return Collection<int,AcademicYear>
     */
    protected function yearOptions(): Collection
    {
        return AcademicYear::query()->orderByDesc('start_date')->get();
    }

    protected function year(): ?AcademicYear
    {
        return AcademicYear::find($this->academicYearId)
            ?? AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
    }

    protected function period(?AcademicYear $year): ?RecapPeriod
    {
        if ($year === null) {
            $this->periodError = null;

            return null;
        }

        try {
            $periode = $this->periodType === 'daily'
                ? RecapPeriod::daily(Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay())
                : RecapPeriod::monthly((int) substr($this->month, 0, 4), (int) substr($this->month, 5, 2));

            $periode->assertWithin($year);
        } catch (RecapPeriodOutOfRange $exception) {
            $this->periodError = $exception->getMessage();

            return null;
        }

        $this->periodError = null;

        return $periode;
    }
}
