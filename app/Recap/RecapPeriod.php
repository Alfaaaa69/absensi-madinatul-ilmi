<?php

namespace App\Recap;

use App\Models\AcademicYear;
use Illuminate\Support\Carbon;

final class RecapPeriod
{
    private function __construct(
        public readonly string $type,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    public static function daily(Carbon $date): self
    {
        $awal = $date->copy()->startOfDay();

        return new self('daily', $awal, $awal->copy());
    }

    public static function monthly(int $year, int $month): self
    {
        $awal = Carbon::create($year, $month, 1)->startOfDay();

        return new self('monthly', $awal, $awal->copy()->endOfMonth()->startOfDay());
    }

    public function isWithin(AcademicYear $academicYear): bool
    {
        return $this->start->greaterThanOrEqualTo($academicYear->start_date->copy()->startOfDay())
            && $this->end->lessThanOrEqualTo($academicYear->end_date->copy()->endOfDay());
    }

    public function assertWithin(AcademicYear $academicYear): void
    {
        if (! $this->isWithin($academicYear)) {
            throw RecapPeriodOutOfRange::for($this, $academicYear);
        }
    }

    public function label(): string
    {
        return $this->type === 'daily'
            ? $this->start->translatedFormat('j F Y')
            : $this->start->translatedFormat('F Y');
    }

    /**
     * Batas atas yang benar-benar boleh dihitung.
     *
     * Admin boleh mengisi absensi untuk tanggal yang belum terjadi, jadi
     * `end` sendiri bisa berada di masa depan. Menghitung catatan seperti itu
     * membuat rekap bulan berjalan menampilkan alpa untuk hari yang belum
     * terjadi, dan laporan yang dicetak kepala sekolah jadi tidak sesuai
     * kenyataan saat dicetak.
     *
     * Seluruh aturan ini hidup di satu method supaya matriks dan drill-down
     * tidak bisa punya batas atas yang berbeda: kalau salah satu memakai
     * `$this->end` langsung, angka di daftar riwayat dan angka di tabel
     * akan berbeda untuk absensi yang sama.
     *
     * `null` berarti tidak satu pun bagian periode ini sudah terjadi, jadi
     * tidak ada yang boleh dihitung sama sekali.
     */
    public function countedEnd(): ?Carbon
    {
        $hariIni = Carbon::today()->endOfDay();

        if ($this->start->isAfter($hariIni)) {
            return null;
        }

        return $this->end->greaterThan($hariIni) ? $hariIni : $this->end;
    }

    /**
     * Keterangan bahwa periode ini belum selesai, atau `null` kalau sudah.
     *
     * `countedEnd()` diam-diam memotong hitungan di tanggal hari ini. Tanpa
     * keterangan ini, laporan bulan September yang diunduh tanggal 15 tetap
     * menulis "Periode: September 2025", dan angka yang sama dihitung ulang di
     * akhir bulan tidak akan cocok - padahal keduanya sama-sama sahih.
     * Menyebutkan batasnya membuat perbedaan itu terlihat dari laporan itu
     * sendiri.
     *
     * Periode yang belum mulai sama sekali tidak diberi keterangan: tidak ada
     * satu pun tanggal yang terhitung, dan itu sudah tercermin dari laporan
     * kosong.
     */
    public function countedThroughNote(): ?string
    {
        $akhir = $this->countedEnd();

        if ($akhir === null || $this->end->lessThanOrEqualTo($akhir)) {
            return null;
        }

        return 'Data per '.$akhir->translatedFormat('j F Y');
    }
}
