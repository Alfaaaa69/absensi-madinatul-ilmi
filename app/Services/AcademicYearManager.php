<?php

namespace App\Services;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicYearManager
{
    /**
     * @param  array{name: string, period: string, start_date: string, end_date: string, is_active: bool}  $data
     */
    public function save(?AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $mulai = Carbon::parse($data['start_date'])->startOfDay();
            $selesai = Carbon::parse($data['end_date'])->startOfDay();

            $this->tolakRentangBertindih($mulai, $selesai, $academicYear?->id);

            $tahun = $academicYear ?? new AcademicYear;

            $tahun->fill([
                'name' => $data['name'],
                'period' => AcademicPeriod::from($data['period']),
                'start_date' => $mulai->toDateString(),
                'end_date' => $selesai->toDateString(),
                'is_active' => (bool) $data['is_active'],
            ]);
            $tahun->save();

            if ($tahun->is_active) {
                $this->matikanLainnya($tahun->id);
            }

            return $tahun;
        });
    }

    public function setActive(AcademicYear $academicYear, bool $aktif): void
    {
        DB::transaction(function () use ($academicYear, $aktif): void {
            if ($aktif) {
                $this->matikanLainnya($academicYear->id);
            }

            $academicYear->update(['is_active' => $aktif]);
        });
    }

    private function matikanLainnya(string $kecualiId): void
    {
        AcademicYear::query()
            ->whereKeyNot($kecualiId)
            ->update(['is_active' => false]);
    }

    private function tolakRentangBertindih(Carbon $mulai, Carbon $selesai, ?string $kecualiId): void
    {
        $bertindih = AcademicYear::query()
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->whereDate('start_date', '<=', $selesai->toDateString())
            ->whereDate('end_date', '>=', $mulai->toDateString())
            ->exists();

        if ($bertindih) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang tanggal bertindih dengan tahun ajaran yang sudah ada.',
            ]);
        }
    }
}
