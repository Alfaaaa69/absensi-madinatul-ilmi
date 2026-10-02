<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\HomeroomClassRecap;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\RecapScenario;
use Tests\TestCase;

class HomeroomClassRecapDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuka_siswa_menampilkan_status_per_tanggal(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)
            ->assertSee('Riwayat Absensi')->assertSee('1 September 2025')
            ->assertSee('Matematika')->assertSee('Izin')->assertSee('Surat dokter');
    }

    public function test_matriks_tidak_membocorkan_siswa_kelas_lain(): void
    {
        $s = RecapScenario::waliKelas();
        $kelasLain = SchoolClass::factory()->create([
            'academic_year_id' => $s->tahun->id,
            'name' => 'IX-B',
        ]);
        RecapScenario::siswaDi($kelasLain, 'Siti Rahma');

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->assertSee('Ahmad Fauzi')->assertDontSee('Siti Rahma');
    }

    public function test_wali_tidak_bisa_membuka_siswa_dari_kelas_lain(): void
    {
        $s = RecapScenario::waliKelas();
        $luar = RecapScenario::siswaDi(SchoolClass::factory()->create([
            'academic_year_id' => $s->tahun->id,
            'name' => 'IX-B',
        ]), 'Siti Rahma');

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $luar->id)
            ->assertSee('Riwayat siswa tidak tersedia.')->assertDontSee('Surat dokter');
    }

    public function test_riwayat_mencakup_mapel_guru_lain(): void
    {
        $s = RecapScenario::waliKelas();
        $guruLain = User::factory()->guru()->create();
        RecapScenario::absen($s->siswa, RecapScenario::mapelLain($s, 'Fisika', $guruLain), '2025-09-03', AttendanceStatus::Hadir, null, $guruLain);

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)
            ->assertSee('Fisika')->assertSee('Hadir');
    }

    public function test_menutup_riwayat_kembali_ke_matriks(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->call('lihatSiswa', $s->siswa->id)->assertSet('studentId', $s->siswa->id)
            ->call('tutupRiwayat')->assertSet('studentId', null)
            ->assertDontSee('Riwayat Absensi');
    }
}
