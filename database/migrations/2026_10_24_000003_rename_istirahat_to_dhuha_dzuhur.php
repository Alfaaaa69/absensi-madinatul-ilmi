<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_breaks')
            ->where('label', 'Istirahat')
            ->whereIn('start_time', ['10:05:00', '10:10:00'])
            ->update(['label' => 'Istirahat / Dhuha', 'updated_at' => now()]);

        DB::table('school_breaks')
            ->where('label', 'Istirahat / Sholat')
            ->whereIn('start_time', ['11:45:00', '12:20:00'])
            ->update(['label' => 'Istirahat / Dzuhur', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('school_breaks')
            ->where('label', 'Istirahat / Dhuha')
            ->update(['label' => 'Istirahat', 'updated_at' => now()]);

        DB::table('school_breaks')
            ->where('label', 'Istirahat / Dzuhur')
            ->update(['label' => 'Istirahat / Sholat', 'updated_at' => now()]);
    }
};
