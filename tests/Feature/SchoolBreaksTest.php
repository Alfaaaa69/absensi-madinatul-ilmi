<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\SchoolBreak;
use App\Support\SchoolBreaks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolBreaksTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_awal_jeda_senin_tersedia(): void
    {
        $this->assertSame([
            ['after' => 0, 'label' => 'Upacara', 'start' => '07:00', 'end' => '07:45'],
            ['after' => 4, 'label' => 'Istirahat / Dhuha', 'start' => '10:10', 'end' => '10:25'],
            ['after' => 7, 'label' => 'Istirahat / Dzuhur', 'start' => '12:20', 'end' => '12:50'],
        ], SchoolBreaks::forDay(DayOfWeek::Senin));
    }

    public function test_istirahat_ada_di_setiap_hari_sekolah(): void
    {
        foreach ([DayOfWeek::Selasa, DayOfWeek::Rabu, DayOfWeek::Kamis, DayOfWeek::Jumat] as $hari) {
            $this->assertSame([
                ['after' => 4, 'label' => 'Istirahat / Dhuha', 'start' => '10:10', 'end' => '10:25'],
                ['after' => 7, 'label' => 'Istirahat / Dzuhur', 'start' => '11:45', 'end' => '12:25'],
            ], SchoolBreaks::forDay($hari));
        }
    }

    public function test_jeda_baru_dari_database_ikut_terbaca(): void
    {
        SchoolBreak::factory()->create([
            'day_of_week' => DayOfWeek::Rabu,
            'label' => 'Jeda Rabu',
            'start_time' => '09:00',
            'end_time' => '09:15',
            'after' => 2,
        ]);

        $this->assertSame([
            ['after' => 2, 'label' => 'Jeda Rabu', 'start' => '09:00', 'end' => '09:15'],
            ['after' => 4, 'label' => 'Istirahat / Dhuha', 'start' => '10:10', 'end' => '10:25'],
            ['after' => 7, 'label' => 'Istirahat / Dzuhur', 'start' => '11:45', 'end' => '12:25'],
        ], SchoolBreaks::forDay(DayOfWeek::Rabu));
    }

    public function test_overlap_memakai_jeda_dari_database(): void
    {
        $this->assertSame('Istirahat / Dhuha', SchoolBreaks::overlap(DayOfWeek::Senin, '10:15', '10:45')['label']);
        $this->assertNull(SchoolBreaks::overlap(DayOfWeek::Senin, '10:35', '11:10'));
    }
}
