<?php

namespace Tests\Feature\Recap;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Recap\Queries\AdminRecapQuery;
use App\Recap\RecapPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecapQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_rekap_besar_tetap_kecil_jumlah_querynya(): void
    {
        $tahun = AcademicYear::factory()->create([
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
        ]);

        $kelas = SchoolClass::factory()->count(2)->create(['academic_year_id' => $tahun->id])->first();

        foreach (Subject::factory()->count(8)->create() as $mapel) {
            Schedule::factory()->create([
                'class_id' => $kelas->id,
                'subject_id' => $mapel->id,
            ]);
        }

        Student::factory()->count(30)->create(['class_id' => $kelas->id]);

        DB::enableQueryLog();

        $hasil = app(AdminRecapQuery::class)
            ->forAcademicYear($tahun)
            ->run(RecapPeriod::monthly(2025, 9));

        $jumlah = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(30, $hasil->rows->count());
        $this->assertLessThan(10, $jumlah, "Rekap 30 siswa x 8 mapel memakai {$jumlah} query, angka ini harus konstan terhadap jumlah siswa");
    }
}
