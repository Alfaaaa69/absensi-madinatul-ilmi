<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $sekarang = now();

        // Senin memakai jam pelajaran pendek; 10:10–10:25 tetap masuk jeda yang sama.
        DB::table('school_breaks')
            ->where('day_of_week', 1)
            ->where('label', 'Istirahat')
            ->where('start_time', '10:05:00')
            ->update([
                'start_time' => '10:10:00',
                'end_time' => '10:25:00',
                'updated_at' => $sekarang,
            ]);

        $jeda = [
            ['label' => 'Istirahat', 'start_time' => '10:10:00', 'end_time' => '10:25:00', 'after' => 2],
            ['label' => 'Istirahat / Sholat', 'start_time' => '11:45:00', 'end_time' => '12:25:00', 'after' => 3],
        ];

        foreach ([2, 3, 4, 5] as $hari) {
            foreach ($jeda as $item) {
                $bentrok = $this->bentrok('school_breaks', $hari, $item['start_time'], $item['end_time'])
                    || $this->bentrok('schedules', $hari, $item['start_time'], $item['end_time']);

                if ($bentrok) {
                    continue;
                }

                DB::table('school_breaks')->insert([
                    'id' => Str::uuid()->toString(),
                    'day_of_week' => $hari,
                    'label' => $item['label'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'after' => $item['after'],
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('school_breaks')
            ->where('day_of_week', 1)
            ->where('label', 'Istirahat')
            ->where('start_time', '10:10:00')
            ->update([
                'start_time' => '10:05:00',
                'end_time' => '10:35:00',
                'updated_at' => now(),
            ]);

        DB::table('school_breaks')
            ->whereIn('day_of_week', [2, 3, 4, 5])
            ->whereIn('label', ['Istirahat', 'Istirahat / Sholat'])
            ->whereIn('start_time', ['10:10:00', '11:45:00'])
            ->delete();
    }

    private function bentrok(string $tabel, int $hari, string $mulai, string $selesai): bool
    {
        return DB::table($tabel)
            ->where('day_of_week', $hari)
            ->get(['start_time', 'end_time'])
            ->contains(fn ($baris) => $this->detik($baris->start_time) < $this->detik($selesai)
                && $this->detik($mulai) < $this->detik($baris->end_time));
    }

    private function detik(string $waktu): int
    {
        [$jam, $menit, $detik] = array_pad(explode(':', $waktu), 3, '0');

        return ((int) $jam) * 3600 + ((int) $menit) * 60 + (int) $detik;
    }
};
