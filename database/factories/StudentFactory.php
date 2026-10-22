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

    private static array $firstNames = [
        'Ahmad', 'Muhammad', 'Mohamad', 'M.', 'Dewi', 'Putri', 'Rina', 'Siti', 'Dewa', 'Gede',
        'Agus', 'Budi', 'Citra', 'Dian', 'Eka', 'Fajar', 'Galang', 'Hana', 'Indra', 'Joko',
        'Kurnia', 'Lina', 'Mita', 'Nur', 'Oki', 'Putra', 'Rudi', 'Santi', 'Toni', 'Udin',
        'Wahyu', 'Xavier', 'Yudi', 'Zaki', 'Ade', 'Bayu', 'Cakra', 'Darma', 'Edi', 'Felix',
    ];

    private static array $lastNames = [
        'Saputra', 'Sutrisno', 'Wijaya', 'Santoso', 'Pratama', 'Putra', 'Nugraha', 'Setiawan',
        'Kusuma', 'Maulana', 'Hidayat', 'Rosidi', 'Permana', 'Gunawan', 'Wibowo', 'Saputro',
        'Pamungkas', 'Susanto', 'Ferdiansyah', 'Sulistyo', 'Nuryanto', 'Kuswandari', 'Suryani',
        'Lestari', 'Dewi', 'Putri', 'Rahayu', 'Anggraini', 'Sari', 'Mulyani', 'Setyawati',
        'Wulandari', 'Sari', 'Dewanti', 'Rahmawati', 'Sulistyorini', 'Puspasari', 'Dian', 'Sari',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->randomElement(self::$firstNames);
        $lastName = fake()->randomElement(self::$lastNames);

        // Tambah random middle name atau inisial untuk variasi
        $middle = '';
        if (fake()->boolean(30)) {
            $middle = ' ' . fake()->randomElement(self::$firstNames);
        } elseif (fake()->boolean(20)) {
            $middle = ' ' . strtoupper(fake()->randomElement(self::$firstNames)[0]) . '.';
        }

        return [
            'class_id' => SchoolClass::factory(),
            'nisn' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'name' => trim($firstName . $middle . ' ' . $lastName),
            'gender' => fake()->randomElement(StudentGender::cases()),
        ];
    }
}
