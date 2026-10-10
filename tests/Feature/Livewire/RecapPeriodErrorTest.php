<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminAttendanceReport;
use App\Livewire\HomeroomClassRecap;
use App\Livewire\TeacherSubjectRecap;
use App\Models\User;
use App\Recap\RecapPeriod;
use App\Recap\RecapPeriodOutOfRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertSee('berada di luar tahun ajaran');
    }

    /**
     * Aturan "pilih Juli harus error" berlaku untuk pilihan pengguna. Nilai bawaan
     * yang sendiri di luar tahun ajaran hanya membuat setiap pengguna membuka
     * halaman pada kondisi error, sebelum sempat memilih apa pun.
     *
     * Tahun ajaran skenario: 2025-08-01 sampai 2026-06-30.
     */
    public function test_bulan_bawaan_dijepit_ke_awal_tahun_ajaran(): void
    {
        $s = RecapScenario::waliKelas();

        // Setelah skenario: `RecapScenario` memindahkan waktu ke September
        // 2025, jadi "sekarang" harus dipasang ulang sesudahnya.
        Carbon::setTestNow(Carbon::parse('2025-06-20'));

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSet('month', '2025-08')
            ->assertDontSee('berada di luar tahun ajaran');
    }

    public function test_bulan_bawaan_dijepit_ke_akhir_tahun_ajaran(): void
    {
        $s = RecapScenario::waliKelas();

        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSet('month', '2026-06')
            ->assertDontSee('berada di luar tahun ajaran');
    }

    public function test_bulan_bawaan_tidak_diubah_saat_sudah_dalam_tahun_ajaran(): void
    {
        $s = RecapScenario::waliKelas();

        Carbon::setTestNow(Carbon::parse('2025-10-05'));

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSet('month', '2025-10')
            ->assertDontSee('berada di luar tahun ajaran');
    }

    /** Tanpa tahun ajaran sama sekali, tidak ada yang bisa dijepit. */
    public function test_tanpa_tahun_ajaran_bulan_bawaan_tetap_bulan_ini(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminAttendanceReport::class)
            ->assertSet('academicYearId', '')
            ->assertSet('month', '2026-07')
            ->assertDontSee('berada di luar tahun ajaran');
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
