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
            ->assertSee('1')->assertSee('Hadir 1')->assertSee('Izin 1');
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

    /**
     * "Jadwal Saya" hanya untuk guru, sama seperti di sidebar. Admin mengelola
     * jadwal lewat Data Master, jadi tautan itu tidak boleh muncul di halaman
     * rekap miliknya.
     */
    public function test_admin_tidak_lihat_link_jadwal_saya_di_halaman_rekap(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rekap.index'))
            ->assertOk()
            ->assertDontSee(route('jadwal.index'), escape: false)
            ->assertDontSee('Jadwal Saya');
    }

    public function test_guru_melihat_link_jadwal_saya_di_halaman_rekap(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('rekap.index'))
            ->assertOk()
            ->assertSee(route('jadwal.index'), escape: false);
    }

    /**
     * "Mapel Saya" adalah rekap mapel yang diampu guru. Admin tidak mengajar,
     * jadi tab itu tidak relevan untuknya — sama seperti "Jadwal Saya".
     */
    public function test_admin_tidak_melihat_tab_mapel_saya_di_halaman_rekap(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('rekap.index'))
            ->assertOk()
            ->assertDontSee('Mapel Saya');
    }

    public function test_guru_melihat_tab_mapel_saya_di_halaman_rekap(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('rekap.index'))
            ->assertOk()
            ->assertSee('Mapel Saya');
    }

    public function test_halaman_rekap_memiliki_tab_interaktif_alpine(): void
    {
        $s = RecapScenario::waliKelas();

        $this->actingAs($s->wali)
            ->get(route('rekap.index'))
            ->assertOk()
            ->assertSeeHtml('x-data="{ activeTab:')
            ->assertSeeHtml('@click="activeTab =')
            ->assertSeeHtml('x-show="activeTab ===');
    }

    public function test_rekap_kelas_saya_menampilkan_badge_dan_total(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Wali Kelas: VII-A')
            ->assertSee('Total')
            ->assertSee('1');
    }
}
