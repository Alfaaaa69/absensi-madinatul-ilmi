<?php

namespace App\Models;

use App\Enums\StudentGender;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $class_id
 * @property string $nisn
 * @property string $name
 * @property StudentGender $gender
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['class_id', 'nisn', 'name', 'gender'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gender' => StudentGender::class];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
