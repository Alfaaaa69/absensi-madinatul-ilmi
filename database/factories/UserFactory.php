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
            'must_change_password' => false,
            'role' => UserRole::Guru,
            'is_active' => true,
            // Wajib ada walau null: Auth::logout() membaca remember_token.
            // Tanpa ini, model hasil factory() tidak punya atribut itu sehingga
            // berbeda dari model yang dimuat dari database, dan Model::shouldBeStrict()
            // akan meledak saat test memakai actingAs() lalu logout.
            'remember_token' => null,
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
