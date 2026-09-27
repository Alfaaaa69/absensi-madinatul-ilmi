<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Sengaja menulis ke DB: siswa harus dibuat di kelas yang sama dengan jadwal,
     * kalau tidak setiap test yang memvalidasi keanggotaan kelas akan gagal karena
     * factory bukan sumber kebenaran relasi.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jadwal = Schedule::factory()->create();
        $siswa = Student::factory()->create(['class_id' => $jadwal->class_id]);

        return [
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'note' => null,
            'recorded_by' => $jadwal->teacher_id,
            'updated_by' => null,
        ];
    }
}
