<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TeacherSubjectRecap;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class TeacherSubjectRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_menampilkan_mapel_dan_siswa_yang_diajar(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Matematika')->assertSee('Ahmad Fauzi')->assertSee('1/2');
    }

    public function test_mapel_guru_lain_tidak_muncul(): void
    {
        $s = RecapScenario::waliKelas();
        RecapScenario::mapelLain($s, 'Fisika', User::factory()->guru()->create());

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Matematika')->assertDontSee('Fisika');
    }

    public function test_guru_tanpa_jadwal_menampilkan_pesan_kosong(): void
    {
        RecapScenario::waliKelas();
        Schedule::query()->delete();

        Livewire::actingAs(User::factory()->guru()->create())->test(TeacherSubjectRecap::class)
            ->assertSee('Belum ada jadwal mengajar pada tahun ajaran ini.');
    }

    public function test_hanya_menyediakan_filter_bulanan(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Bulan')->assertDontSee('Harian');
    }

    public function test_periode_harian_tidak_bisa_dibuka_lewat_request(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->set('periodType', 'daily')
            ->assertSet('periodType', 'monthly');
    }
}
