<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRecapQueryTest extends TestCase
{
    use RefreshDatabase;

    private function tahun(): AcademicYear
    {
        return AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);
    }

    public function test_admin_memilih_kelas_yang_dia_inginkan(): void
    {
        $tahun = $this->tahun();
        $kelasA = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);
        $kelasB = SchoolClass::factory()->create(['academic_year_id' => $tahun->id]);

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelasB)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(1, $hasil->classes);
        $this->assertSame($kelasB->id, $hasil->classes->first()->id);
        $this->assertNotSame($kelasA->id, $hasil->classes->first()->id);
    }

    public function test_admin_tanpa_kelas_terpilih_mengembalikan_semua_kelas(): void
    {
        $tahun = $this->tahun();
        SchoolClass::factory()->count(3)->create(['academic_year_id' => $tahun->id]);

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $this->assertCount(3, $hasil->classes);
    }

    public function test_admin_tidak_bisa_memilih_kelas_dari_tahun_ajaran_lain(): void
    {
        $tahun = $this->tahun();
        $tahunLalu = AcademicYear::factory()->create([
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);
        $kelasLalu = SchoolClass::factory()->create(['academic_year_id' => $tahunLalu->id]);

        $this->expectException(RecapPeriodOutOfRange::class);

        app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->forClass($kelasLalu)
            ->run(RecapPeriod::monthly(2025, 9));
    }
}
