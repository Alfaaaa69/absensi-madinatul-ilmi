<?php

namespace App\Services;

use App\Enums\HasilHapus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keputusan hapus data master: permanen atau arsip.
 *
 * Satu aturan dipakai untuk semua tipe data — baris hanya boleh hilang
 * permanen kalau tidak ada baris `attendances` yang merujuknya, langsung
 * maupun lewat rantai relasi:
 *
 *   siswa   -> attendances.student_id
 *   jadwal  -> attendances.schedule_id
 *   mapel   -> jadwalnya -> attendances
 *   kelas   -> siswanya atau jadwalnya -> attendances
 *   guru    -> pernah mencatat/mengubah absensi, atau jadwalnya -> attendances
 *
 * Menaruh aturan di satu tempat membuat dua hal sekaligus: riwayat absensi
 * tidak pernah ikut terhapus, dan kita tidak pernah menabrak foreign key
 * `restrictOnDelete` di tabel attendances.
 */
final class MasterDeletion
{
    public function hapus(Model $record): HasilHapus
    {
        return DB::transaction(function () use ($record): HasilHapus {
            if ($this->punyaRiwayatAbsensi($record)) {
                $record->delete();

                return HasilHapus::Diarsipkan;
            }

            $record->forceDelete();

            return HasilHapus::Permanen;
        });
    }

    private function punyaRiwayatAbsensi(Model $record): bool
    {
        if ($record instanceof Student) {
            return Attendance::query()
                ->where('student_id', $record->id)
                ->exists();
        }

        if ($record instanceof Schedule) {
            return Attendance::query()
                ->where('schedule_id', $record->id)
                ->exists();
        }

        // whereHas memakai scope global relasi, dan relasi attendance ke
        // jadwal/siswa ber-SoftDeletes — jadi tanpa withTrashed() anak yang
        // sudah terarsip hilang dari hasilnya. Induknya lalu dihapus permanen,
        // kasade menabrak restrictOnDelete, dan admin mendapat 500.
        if ($record instanceof Subject) {
            return Attendance::query()
                ->whereHas('schedule', fn ($query) => $query->withTrashed()->where('subject_id', $record->id))
                ->exists();
        }

        if ($record instanceof SchoolClass) {
            return Attendance::query()
                ->whereHas('schedule', fn ($query) => $query->withTrashed()->where('class_id', $record->id))
                ->orWhereHas('student', fn ($query) => $query->withTrashed()->where('class_id', $record->id))
                ->exists();
        }

        if ($record instanceof User) {
            return Attendance::query()
                ->where('recorded_by', $record->id)
                ->orWhere('updated_by', $record->id)
                ->orWhereHas('schedule', fn ($query) => $query->withTrashed()->where('teacher_id', $record->id))
                ->exists();
        }

        throw new InvalidArgumentException(
            sprintf('%s bukan data master yang bisa dihapus.', $record::class),
        );
    }
}
