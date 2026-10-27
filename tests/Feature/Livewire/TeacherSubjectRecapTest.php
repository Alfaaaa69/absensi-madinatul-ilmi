<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\TeacherSubjectRecap;
use App\Models\Schedule;
use App\Models\SchoolClass;
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
            ->assertSee('Matematika')->assertSee('Ahmad Fauzi')->assertSee('50%');
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

    public function test_rekap_guru_menampilkan_kartu_per_kelas(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('VII-A')
            ->assertSee('Matematika')
            ->assertSee('Ahmad Fauzi');
    }

    public function test_rekap_guru_menampilkan_badge_ringkasan_per_kelas(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('Hadir')
            ->assertSee('Izin')
            ->assertSee('Sakit')
            ->assertSee('Alfa');
    }

    public function test_rekap_guru_menampilkan_persentase_kehadiran(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('% Kehadiran')
            ->assertSee('50%');
    }

    public function test_rekap_guru_mengelompokkan_dua_kelas_berbeda(): void
    {
        $s = RecapScenario::waliKelas();
        $kelasB = SchoolClass::factory()->create([
            'academic_year_id' => $s->tahun->id,
            'name' => 'VIII-B',
        ]);
        $siswaB = RecapScenario::siswaDi($kelasB, 'Siti Rahmah');
        $jadwalB = Schedule::factory()->create([
            'class_id' => $kelasB->id,
            'subject_id' => $s->jadwal->subject_id,
            'teacher_id' => $s->wali->id,
        ]);
        RecapScenario::absen($siswaB, $jadwalB, '2025-09-01', AttendanceStatus::Hadir);

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('VII-A')
            ->assertSee('VIII-B')
            ->assertSee('Ahmad Fauzi')
            ->assertSee('Siti Rahmah');
    }

    public function test_kehadiran_rendah_ditandai_merah(): void
    {
        $s = RecapScenario::waliKelas();
        RecapScenario::absen($s->siswa, $s->jadwal, '2025-09-03', AttendanceStatus::Alpa);
        RecapScenario::absen($s->siswa, $s->jadwal, '2025-09-04', AttendanceStatus::Alpa);

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee('25%')
            ->assertSeeHtml('bg-red-50');
    }

    public function test_kartu_kelas_memiliki_toggle_accordion_tertutup(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSeeHtml('x-data="{ open: false }"')
            ->assertSeeHtml('x-show="open"')
            ->assertSee('Lihat Detail');
    }

    public function test_kartu_kelas_menampilkan_detail_hari_dan_jam_mengajar(): void
    {
        $s = RecapScenario::waliKelas();

        Livewire::actingAs($s->wali)->test(TeacherSubjectRecap::class)
            ->assertSee($s->jadwal->day_of_week->label())
            ->assertSee($s->jadwal->start_time->format('H:i'))
            ->assertSee($s->jadwal->end_time->format('H:i'));
    }
}
