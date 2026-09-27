<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pakai word() (return string) dua kali, bukan words(2, true). Faker memberi
        // `words()` tipe `array|string` dan PHPStan tidak bisa menyempitkannya ke
        // string meski argumen kedua true — sehingga ucfirst()/implode() tetap gagal.
        $nama = fake()->unique()->word().' '.fake()->unique()->word();

        return [
            'name' => ucfirst($nama),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
