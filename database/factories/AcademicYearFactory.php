<?php

namespace Database\Factories;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * Default-nya tahun ajaran yang sedang berjalan. Test yang butuh rentang lain
     * meng-override `start_date`/`end_date`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        return [
            'name' => $tahunMulai.'/'.($tahunMulai + 1),
            'period' => now()->month >= 7 && now()->month <= 12
                ? AcademicPeriod::Ganjil
                : AcademicPeriod::Genap,
            'start_date' => sprintf('%d-07-01', $tahunMulai),
            'end_date' => sprintf('%d-06-30', $tahunMulai + 1),
            'is_active' => true,
        ];
    }
}
