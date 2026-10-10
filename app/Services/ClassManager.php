<?php

namespace App\Services;

use App\Enums\HasilHapus;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassManager
{
    public function __construct(private readonly MasterDeletion $deletion) {}

    /**
     * @param  array{academic_year_id: string, name: string, grade_level: string|int, homeroom_teacher_id: string|null}  $data
     */
    public function save(?SchoolClass $schoolClass, array $data): SchoolClass
    {
        return DB::transaction(function () use ($schoolClass, $data) {
            $waliId = $data['homeroom_teacher_id'] === '' || $data['homeroom_teacher_id'] === null
                ? null
                : (string) $data['homeroom_teacher_id'];

            if ($waliId !== null) {
                $this->tolakWaliGanda($waliId, (string) $data['academic_year_id'], $schoolClass?->id);
            }

            $kelas = $schoolClass ?? new SchoolClass;

            $kelas->fill([
                'academic_year_id' => $data['academic_year_id'],
                'name' => $data['name'],
                'grade_level' => (int) $data['grade_level'],
                'homeroom_teacher_id' => $waliId,
            ]);
            $kelas->save();

            return $kelas;
        });
    }

    /**
     * Kelas tanpa riwayat absensi dihapus permanen beserta siswa dan
     * jadwalnya. Kalau ada riwayat, kelasnya diarsipkan.
     */
    public function hapus(SchoolClass $schoolClass): HasilHapus
    {
        return $this->deletion->hapus($schoolClass);
    }

    public function pulihkan(SchoolClass $schoolClass): void
    {
        $schoolClass->restore();
    }

    private function tolakWaliGanda(string $waliId, string $academicYearId, ?string $kecualiId): void
    {
        $sudah = SchoolClass::query()
            ->where('homeroom_teacher_id', $waliId)
            ->where('academic_year_id', $academicYearId)
            ->when($kecualiId, fn ($query) => $query->whereKeyNot($kecualiId))
            ->exists();

        if ($sudah) {
            throw ValidationException::withMessages([
                'homeroom_teacher_id' => 'Guru ini sudah menjadi wali kelas pada tahun ajaran tersebut.',
            ]);
        }
    }
}
