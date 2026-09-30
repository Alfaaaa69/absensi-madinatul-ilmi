<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SENIN = '2026-09-28';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::SENIN, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function masukSebagaiGuru(array $atribut = []): User
    {
        $guru = User::factory()->guru()->create($atribut);

        $this->actingAs($guru);

        return $guru;
    }

    private function masukSebagaiAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        return $admin;
    }

    public function test_guru_pengajar_bisa_membuka_halaman_absensi(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->get(route('absensi.create', $jadwal))->assertOk();
    }

    public function test_guru_mengajar_jadwal_lain_mendapat_403(): void
    {
        $this->masukSebagaiGuru();
        $jadwalLain = Schedule::factory()->create();

        $this->get(route('absensi.create', $jadwalLain))->assertForbidden();
    }

    /**
     * Dulu halaman ini 403. Sekarang guru yang membuka tanggal terlalu lama
     * tetap melihat halaman — dengan kalimat yang bilang apa yang terjadi dan
     * siapa yang bisa membantu. Status 200 di sini bukan longgarnya aturan:
     * `SchedulePolicy::recordAttendance` tetap menolak, dan `simpan()` masih
     * menolak di detik yang sama seperti yang dibuktikan di
     * `AttendanceEntryWindowTest`.
     */
    public function test_guru_tidak_bisa_membuka_tanggal_di_luar_jendela(): void
    {
        $guru = $this->masukSebagaiGuru();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->get(route('absensi.show', [$jadwal, today()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertSee('Absensi ini sudah lewat 1 hari. Hubungi admin.')
            ->assertDontSee('Simpan Absensi');
    }

    /**
     * Akun nonaktif ditolak oleh EnsureUserIsActive, bukan oleh SchedulePolicy,
     * jadi hasilnya redirect ke login — bukan 403. Yang diuji di sini adalah
     * niatnya: session yang masih hidup tidak cukup untuk sampai ke form absensi,
     * dan penolakan itu harus berupa pesan berbahasa manusia, bukan 403 telanjang
     * yang tidak memberi tahu guru harus menghubungi siapa.
     */
    public function test_akun_tidak_aktif_diarahkan_kembali_ke_login_dengan_pesan(): void
    {
        $guru = $this->masukSebagaiGuru(['is_active' => false]);
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        $this->get(route('absensi.create', $jadwal))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'nuptk' => 'Akun ini sudah dinonaktifkan. Hubungi admin.',
            ]);

        $this->assertGuest();
        $this->assertSame(0, Attendance::count());
    }

    public function test_admin_bisa_membuka_halaman_absensi_jadwal_siapa_pun(): void
    {
        $admin = $this->masukSebagaiAdmin();
        $jadwal = Schedule::factory()->create();

        $this->get(route('absensi.create', $jadwal))->assertOk();

        Livewire::actingAs($admin)
            ->test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk();
    }
}
