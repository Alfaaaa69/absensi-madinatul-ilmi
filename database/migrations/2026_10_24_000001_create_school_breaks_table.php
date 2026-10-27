<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tinyInteger('day_of_week');
            $table->string('label', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->tinyInteger('after');
            $table->timestamps();

            $table->index(['day_of_week', 'after']);
        });

        $sekarang = now();

        DB::table('school_breaks')->insert([
            [
                'id' => Str::uuid()->toString(),
                'day_of_week' => 1,
                'label' => 'Upacara',
                'start_time' => '07:00:00',
                'end_time' => '07:45:00',
                'after' => 0,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
            [
                'id' => Str::uuid()->toString(),
                'day_of_week' => 1,
                'label' => 'Istirahat',
                'start_time' => '10:05:00',
                'end_time' => '10:35:00',
                'after' => 4,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
            [
                'id' => Str::uuid()->toString(),
                'day_of_week' => 1,
                'label' => 'Istirahat / Sholat',
                'start_time' => '12:20:00',
                'end_time' => '12:50:00',
                'after' => 7,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('school_breaks');
    }
};
