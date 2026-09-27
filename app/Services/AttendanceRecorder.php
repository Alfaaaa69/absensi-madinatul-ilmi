<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AttendanceRecorder
{
    /**
     * Satu-satunya pintu tulis ke tabel attendances.
     *
     * Otorisasi tidak diduplikasi di sini: baris baru lewat
     * SchedulePolicy::recordAttendance, baris yang sudah ada lewat
     * AttendancePolicy::update. Kalau aturan berubah, hanya Policy yang
     * disentuh.
     *
     * @param  Collection<int, array{student_id: string, status: AttendanceStatus|string, note?: string|null}>  $rows
     */
    public function record(Schedule $schedule, Carbon $date, Collection $rows, User $actor): int
    {
        Gate::forUser($actor)->authorize('recordAttendance', [$schedule, $date]);

        $idsSiswa = $schedule->schoolClass->students()->pluck('id')->all();

        return (int) DB::transaction(function () use ($schedule, $date, $rows, $actor, $idsSiswa) {
            $tersimpan = 0;

            foreach ($rows as $row) {
                $studentId = $row['student_id'];

                $status = $row['status'] instanceof AttendanceStatus
                    ? $row['status']
                    : AttendanceStatus::from($row['status']);

                if (! in_array($studentId, $idsSiswa, true)) {
                    throw ValidationException::withMessages([
                        'student_id' => "Siswa {$studentId} bukan anggota kelas {$schedule->class_id}.",
                    ]);
                }

                $existing = Attendance::query()
                    ->where('student_id', $studentId)
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($existing === null) {
                    Attendance::create([
                        'student_id' => $studentId,
                        'schedule_id' => $schedule->id,
                        'date' => $date->toDateString(),
                        'status' => $status,
                        'note' => $row['note'] ?? null,
                        'recorded_by' => $actor->id,
                    ]);

                    $tersimpan++;
                } else {
                    $existing->setRelation('schedule', $schedule);

                    Gate::forUser($actor)->authorize('update', $existing);

                    $existing->update([
                        'status' => $status,
                        'note' => $row['note'] ?? null,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            return $tersimpan;
        });
    }
}
