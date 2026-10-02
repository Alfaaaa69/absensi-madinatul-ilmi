<?php

namespace Tests\Feature\Livewire;

use App\Livewire\HomeroomClassRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class HomeroomClassRecapTest extends TestCase
{
    use RefreshDatabase;

    public function test_menampilkan_rekap_bulanan_kelas_wali(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSet('periodType', 'monthly')
            ->assertSee('VII-A')->assertSee('Ahmad Fauzi')
            ->assertSee('1/2')->assertSee('Hadir 1')->assertSee('Izin 1');
    }

    public function test_bulan_di_luar_tahun_ajaran_menampilkan_pesan_error(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('month', '2025-07')
            ->assertSet('periodError', fn ($pesan) => is_string($pesan) && $pesan !== '');
    }

    public function test_wali_dengan_dua_kelas_menampilkan_pemilih_kelas(): void
    {
        $s = RecapScenario::waliKelas();
        $kedua = RecapScenario::kelasLain($s, 'VII-B', $s->wali);
        RecapScenario::siswaDi($kedua, 'Budi Santoso');

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Semua kelas')->assertSee('VII-B')
            ->set('classId', $kedua->id)
            ->assertSee('Budi Santoso')->assertDontSee('Ahmad Fauzi');
    }

    public function test_guru_bukan_wali_tidak_lihat_tab_kelas_saya(): void
    {
        RecapScenario::waliKelas();

        $this->actingAs(User::factory()->guru()->create())->get(route('rekap.index'))
            ->assertOk()->assertDontSee('Kelas Saya');
    }
}
