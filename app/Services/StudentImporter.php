<?php

namespace App\Services;

use App\Enums\StudentGender;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StudentImporter
{
    public const UKURAN_CHUNK = 100;

    /**
     * @param  array<int, array<string, mixed>>  $baris
     */
    public function import(array $baris): int
    {
        $potongan = array_chunk($baris, self::UKURAN_CHUNK);

        return (int) DB::transaction(function () use ($potongan): int {
            $tersimpan = 0;

            foreach ($potongan as $chunk) {
                $waktu = now();

                Student::query()->insert(array_map(
                    fn (array $baris) => $this->baris($baris, $waktu),
                    $chunk
                ));

                $tersimpan += count($chunk);
            }

            return $tersimpan;
        });
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    private function baris(array $baris, Carbon $waktu): array
    {
        $gender = (string) $baris['gender'];
        $classId = (string) ($baris['class_id'] ?? '');

        if (! in_array($gender, array_map(fn (StudentGender $item) => $item->value, StudentGender::cases()), true)) {
            throw ValidationException::withMessages([
                'file' => "Jenis kelamin tidak valid pada baris {$baris['row']}.",
            ]);
        }

        if ($classId === '') {
            throw ValidationException::withMessages([
                'file' => "Kelas tidak ditemukan pada baris {$baris['row']}.",
            ]);
        }

        return [
            'id' => (string) Str::uuid(),
            'class_id' => $classId,
            'nisn' => (string) $baris['nisn'],
            'name' => (string) $baris['nama'],
            'gender' => $gender,
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ];
    }
}
