<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\SchoolBreakFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property DayOfWeek $day_of_week
 * @property string $label
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property int $after
 */
class SchoolBreak extends Model
{
    /** @use HasFactory<SchoolBreakFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['day_of_week', 'label', 'start_time', 'end_time', 'after'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'after' => 'integer',
        ];
    }
}
