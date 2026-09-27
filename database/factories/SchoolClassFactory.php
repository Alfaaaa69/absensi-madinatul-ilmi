<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'homeroom_teacher_id' => User::factory()->state(['role' => UserRole::Guru]),
            'name' => strtoupper(fake()->unique()->bothify('???-##')),
            'grade_level' => 7,
        ];
    }

    /**
     * Kelas tanpa wali — dashboard wali kelas harus punya empty state (spec §5).
     */
    public function tanpaWali(): static
    {
        return $this->state(fn (): array => ['homeroom_teacher_id' => null]);
    }
}
