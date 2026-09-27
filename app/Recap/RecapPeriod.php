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
}
