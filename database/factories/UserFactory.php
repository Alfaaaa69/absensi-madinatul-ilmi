<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // NUPTK 18 digit, identitas login (D6). Unik per proses test.
            'nuptk' => '10'.fake()->unique()->numerify('################'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Guru,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Admin]);
    }

    public function guru(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Guru]);
    }

    public function tidakAktif(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
