<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Tabel `classes` dipetakan ke model ini, bukan `Class`, karena `class` keyword PHP.
 *
 * @property string $id
 * @property string $academic_year_id
 * @property string|null $homeroom_teacher_id
 * @property string $name
 * @property int $grade_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = ['academic_year_id', 'homeroom_teacher_id', 'name', 'grade_level'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grade_level' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schoolClass): void {
            if (! in_array($schoolClass->grade_level, [7, 8, 9], true)) {
                throw ValidationException::withMessages([
                    'grade_level' => 'Tingkat kelas harus 7, 8, atau 9.',
                ]);
            }
        });
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }
}
