<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_breaks')
            ->whereIn('day_of_week', [2, 3, 4, 5])
            ->where('label', 'Istirahat / Dhuha')
            ->where('start_time', '10:10:00')
            ->update(['after' => 4, 'updated_at' => now()]);

        DB::table('school_breaks')
            ->whereIn('day_of_week', [2, 3, 4, 5])
            ->where('label', 'Istirahat / Dzuhur')
            ->where('start_time', '11:45:00')
            ->update(['after' => 7, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('school_breaks')
            ->whereIn('day_of_week', [2, 3, 4, 5])
            ->where('label', 'Istirahat / Dhuha')
            ->where('start_time', '10:10:00')
            ->update(['after' => 2, 'updated_at' => now()]);

        DB::table('school_breaks')
            ->whereIn('day_of_week', [2, 3, 4, 5])
            ->where('label', 'Istirahat / Dzuhur')
            ->where('start_time', '11:45:00')
            ->update(['after' => 3, 'updated_at' => now()]);
    }
};
