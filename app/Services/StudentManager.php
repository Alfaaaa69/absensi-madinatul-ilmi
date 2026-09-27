<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class StudentManager
{
    /**
     * @param  array{class_id: string, nisn: string, name: string, gender: string}  $data
     */
    public function save(?Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $siswa = $student ?? new Student;

            $siswa->fill([
                'class_id' => $data['class_id'],
                'nisn' => $data['nisn'],
                'name' => $data['name'],
                'gender' => $data['gender'],
            ]);
            $siswa->save();

            return $siswa;
        });
    }

    public function arsipkan(Student $student): void
    {
        $student->delete();
    }

    public function pulihkan(Student $student): void
    {
        $student->restore();
    }
}
