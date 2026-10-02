<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\HomeroomClassRecap;
use App\Models\AcademicYear;
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

    /**
     * `classId` datang dari browser. Spec minta "guru tidak bisa membuka rekap
     * kelas yang bukan dia wali" diuji di level query; yang diuji di sini adalah
     * lapisan yang benar-benar memanggil `Gate::authorize`.
     */
    public function test_class_id_kelas_lain_ditolak(): void
    {
        $s = RecapScenario::waliKelas();
        $kelasLain = SchoolClass::factory()->create([
            'academic_year_id' => $s->tahun->id,
            'name' => 'IX-B',
            'homeroom_teacher_id' => User::factory()->guru()->create()->id,
        ]);

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('classId', $kelasLain->id)
            ->assertForbidden();
    }

    /**
     * Wali yang sama bisa jadi wali dari dua baris `classes` pada tahun berbeda.
     *
     * Tanpa pemeriksaan tahun di policy, kelas ini lolos `viewRecap` lalu
     * ditolak `HomeroomClassRecapQuery::forClass()` dengan
     * `LogicException` - dan harness pengujian Livewire melaporkan status 403
     * untuk exception apa pun, jadi test ini akan hijau karena alasan yang
     * salah. Test di `SchoolClassPolicyTest` yang membuktikan aturan sebenarnya;
     * yang di sini hanya memastikan component meneruskan penolakan itu.
     */
    public function test_class_id_dari_tahun_ajaran_lain_ditolak(): void
    {
        $s = RecapScenario::waliKelas();

        $tahunLalu = AcademicYear::factory()->create([
            'name' => '2024/2025',
            'start_date' => '2024-08-01',
            'end_date' => '2025-06-30',
        ]);
        $kelasLalu = SchoolClass::factory()->create([
            'academic_year_id' => $tahunLalu->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $s->wali->id,
        ]);
        RecapScenario::siswaDi($kelasLalu, 'Budi Santoso Junior');

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('classId', $kelasLalu->id)
            ->assertForbidden()
            ->assertDontSee('Budi Santoso Junior');
    }

    public function test_class_id_memang_miliknya_dipakai(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('classId', $s->kelas->id)
            ->assertOk()
            ->assertSee('Ahmad Fauzi');
    }

    public function test_class_id_tidak_ada_menghasilkan_404(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(HomeroomClassRecap::class)
            ->set('classId', '00000000-0000-4000-8000-000000000000')
            ->assertStatus(404);
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
