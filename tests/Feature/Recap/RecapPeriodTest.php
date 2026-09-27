<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecapPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_periode_harian_membungkus_satu_hari(): void
    {
        $periode = RecapPeriod::daily(Carbon::parse('2025-09-01'));

        $this->assertSame('daily', $periode->type);
        $this->assertSame('2025-09-01', $periode->start->toDateString());
        $this->assertSame('2025-09-01', $periode->end->toDateString());
    }

    public function test_periode_bulanan_membungkus_sebulan_penuh(): void
    {
        $periode = RecapPeriod::monthly(2025, 9);

        $this->assertSame('monthly', $periode->type);
        $this->assertSame('2025-09-01', $periode->start->toDateString());
        $this->assertSame('2025-09-30', $periode->end->toDateString());
    }

    public function test_september_di_dalam_tahun_ajaran_agustus_juni(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertTrue(RecapPeriod::monthly(2025, 9)->isWithin($tahun));
    }

    public function test_juli_di_luar_tahun_ajaran_yang_mulai_agustus(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertFalse(RecapPeriod::monthly(2025, 7)->isWithin($tahun));
    }

    public function test_januari_tahun_berikutnya_masih_di_dalam_ganjil(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->assertTrue(RecapPeriod::monthly(2026, 1)->isWithin($tahun));
    }

    public function test_assert_melempar_kalau_di_luar_tahun_ajaran(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $this->expectException(RecapPeriodOutOfRange::class);

        RecapPeriod::monthly(2025, 7)->assertWithin($tahun);
    }

    public function test_label_bulanan(): void
    {
        $this->assertSame('September 2025', RecapPeriod::monthly(2025, 9)->label());
    }

    public function test_label_harian(): void
    {
        $this->assertSame('1 September 2025', RecapPeriod::daily(Carbon::parse('2025-09-01'))->label());
    }
}
