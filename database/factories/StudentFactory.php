<?php

namespace Database\Factories;

use App\Enums\StudentGender;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'nisn' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(StudentGender::cases()),
        ];
    }
}
