<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tanpa soft delete: koreksi absensi dilakukan dengan mengubah `status` dan mengisi
 * `updated_by`. Menghapus baris absensi berarti menghapus riwayat.
 *
 * @property string $id
 * @property string $student_id
 * @property string $schedule_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property string|null $note
 * @property string $recorded_by
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['student_id', 'schedule_id', 'date', 'status', 'note', 'recorded_by', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
