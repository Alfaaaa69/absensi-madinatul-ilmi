<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory()->state(['role' => UserRole::Guru]),
            'day_of_week' => DayOfWeek::Senin,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ];
    }
}
