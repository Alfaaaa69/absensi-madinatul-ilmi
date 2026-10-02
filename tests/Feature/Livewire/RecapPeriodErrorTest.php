<?php

namespace Tests\Feature\Livewire;

use App\Livewire\HomeroomClassRecap;
use App\Livewire\TeacherSubjectRecap;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class RecapPeriodErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_menampilkan_pesan_periode(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '')
            ->assertSeeHtml('role="alert"');
    }

    public function test_guru_mapel_menampilkan_pesan_periode(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '')
            ->assertSeeHtml('role="alert"');
    }

    public function test_tabel_tidak_dirender_saat_periode_tidak_valid(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Ahmad Fauzi')
            ->set('month', '2025-07')
            ->assertDontSee('Ahmad Fauzi');
    }

    public function test_pesan_menyalin_teks_exception_asli(): void
    {
        $s = RecapScenario::waliKelas();

        try {
            RecapPeriod::monthly(2025, 7)->assertWithin($s->tahun);
            $this->fail('RecapPeriod::monthly(2025, 7) seharusnya berada di luar tahun ajaran.');
        } catch (RecapPeriodOutOfRange $exception) {
            $pesan = $exception->getMessage();
        }

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', $pesan);
    }
}
