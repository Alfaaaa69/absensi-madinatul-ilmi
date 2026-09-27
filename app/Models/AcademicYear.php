<?php

namespace App\Models;

use App\Enums\AcademicPeriod;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property AcademicPeriod $period
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'period', 'start_date', 'end_date', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => AcademicPeriod::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
