<?php

namespace App\Services;

use App\Enums\HasilHapus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

final class StudentManager
{
    public function __construct(private readonly MasterDeletion $deletion) {}

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

    public function hapus(Student $student): HasilHapus
    {
        return $this->deletion->hapus($student);
    }

    public function pulihkan(Student $student): void
    {
        $student->restore();
    }
}
