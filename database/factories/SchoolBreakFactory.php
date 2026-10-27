<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\SchoolBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolBreak>
 */
class SchoolBreakFactory extends Factory
{
    protected $model = SchoolBreak::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => DayOfWeek::Senin,
            'label' => 'Istirahat',
            'start_time' => '10:05:00',
            'end_time' => '10:35:00',
            'after' => 4,
        ];
    }
}
