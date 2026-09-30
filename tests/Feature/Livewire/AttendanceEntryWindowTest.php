<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas edit 1 hari tidak lagi muncul sebagai 403, melainkan halaman terkunci.
 *
 * Aturan bisnisnya tidak berubah di sini — yang berubah adalah cara aturan
 * itu disampaikan. `SchedulePolicy::recordAttendance` tetap satu-satunya
 * tempat aturan hidup; test di sini mengunci bahwa aturan itu disampaikan
 * sebagai kalimat berbahasa manusia plus kontak admin, bukan sebagai halaman
 * forbidden yang tidak memberi tahu siapa yang bisa membantu.
 */
class AttendanceEntryWindowTest extends TestCase
{
    use RefreshDatabase;

    private const SENIN = '2026-09-28';

    private const PESAN_LAMPAU = 'Absensi ini sudah lewat 1 hari. Hubungi admin.';

    private const PESAN_DEPAN = 'Absensi untuk tanggal ini belum bisa diisi.';

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

    private function jadwalMilik(User $guru): Schedule
    {
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        Student::factory()->count(2)->create(['class_id' => $jadwal->class_id]);

        return $jadwal;
    }

    public function test_guru_melihat_pesan_batas_edit_untuk_tanggal_lampau(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', false)
            ->assertSee(self::PESAN_LAMPAU);
    }

    public function test_guru_melihat_pesan_batas_edit_untuk_masa_depan(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->addDay()->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', false)
            ->assertSee(self::PESAN_DEPAN);
    }

    public function test_form_tidak_dirender_saat_jendela_sudah_tertutup(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertDontSee('Simpan Absensi')
            ->assertDontSee('Tandai Semua Hadir')
            ->assertDontSee('Tandai Semua Alpa')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');
    }

    /**
     * `test_form_tidak_dirender_saat_jendela_sudah_tertutup` membuktikan
     * kontrol form-nya hilang, tapi tidak membuktikan isi roster-nya hilang.
     * Dua hal itu terpisah: `mount()` tetap memuat `$baris` dari kelas
     * jadwal, jadi "form tidak dirender" belum otomatis berarti "nama siswa
     * tidak bocor ke HTML".
     *
     * Yang dilindungi di sini adalah halaman terkunci untuk guru yang jadwalnya
     * bukan milikmu. Kalau nama siswa ikut bocor, guru bisa membaca siapa yang
     * absen pada jadwal yang sedang dikunci, padahal dia tidak boleh
     * menyentuhnya sama sekali — itu kebocoran data lintas kelas, bukan sekadar
     * tampilan.
     *
     * Nama diambil dari database, bukan ditulis literal, supaya test ini tetap
     * benar meski `StudentFactory` diganti sumber namanya.
     */
    public function test_halaman_terkunci_tidak_membocorkan_nama_siswa(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);
        $namaSiswa = Student::query()
            ->where('class_id', $jadwal->class_id)
            ->pluck('name');

        $this->assertGreaterThan(0, $namaSiswa->count());

        $component = Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])->assertOk();

        foreach ($namaSiswa as $nama) {
            $component->assertDontSee($nama);
        }
    }

    public function test_kontak_admin_tampil_saat_absensi_terkunci(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        User::factory()->admin()->create([
            'name' => 'Operator Sekolah',
            'email' => 'operator@sekolah.sch.id',
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertSee('Operator Sekolah')
            ->assertSee('operator@sekolah.sch.id')
            ->assertSeeHtml('mailto:operator@sekolah.sch.id');
    }

    /**
     * Daftar kontak yang kosong harus dijelaskan, bukan dirender jadi `<ul>`
     * kosong. `<ul>` tanpa isi terlihat seperti bug, bukan seperti "belum ada
     * admin". Tanpa kalimat penggantinya, guru yang terkunci tidak diberi
     * tujuan lain dan halaman ini diam-diam tidak menawarkan pilihan apa pun.
     */
    public function test_halaman_terkunci_menyatakan_belum_ada_admin_aktif(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->assertSee('Belum ada akun admin yang aktif. Hubungi operator sekolah.');
    }

    public function test_admin_tetap_melihat_form_untuk_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwalMilik($admin);
        $this->actingAs($admin);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(30)->toDateString(),
        ])
            ->assertOk()
            ->assertSet('bolehUbah', true)
            ->assertSee('Simpan Absensi')
            ->assertDontSee(self::PESAN_LAMPAU);
    }

    /**
     * Halaman terkunci tanpa form saja belum cukup: jalur simpannya juga
     * harus menolak. Kalau tidak, guru yang memaksa `simpan()` dari snapshot
     * akan tetap menulis baris `attendances` untuk tanggal yang sudah lewat —
     * halaman terlihat terkunci, datanya tidak. Gate-nya ada di `simpan()`
     * tepat karena alasan ini.
     *
     * Pesan yang diharapkan adalah kalimat jendela, bukan "Kamu tidak
     * berwenang" dari `AttendanceRecorder`: keduanya menolak, tapi yang
     * kedua menyalahkan orang, bukan aturan jendelanya.
     */
    public function test_simpan_di_halaman_terkunci_menolak_dengan_pesan_jendela(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas] = $this->kelasDenganDuaSiswa();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ])
            ->assertOk()
            ->call('simpan')
            ->assertHasErrors('form')
            ->assertSee(self::PESAN_LAMPAU)
            ->assertDontSee('Kamu tidak berwenang mengisi absensi jadwal ini. Hubungi admin.');

        $this->assertSame(0, Attendance::count());
    }

    /**
     * `bolehUbah` adalah properti publik, jadi tanpa `#[Locked]` nilainya
     * bisa dikirim balik oleh klien dan gate `if (! $this->bolehUbah)` akan
     * dilewati begitu saja dengan mengarang satu field.
     *
     * Yang diuji di sini bukan detail mekanisme Livewire, tapi akibatnya:
     * nilai yang dikirim tidak berlaku. `HandleComponents::updateProperty()`
     * memang sempat menaruh nilai baru ke properti sebelum callback
     * `update()` dilempar, tapi exception-nya naik keluar dari request, jadi
     * request itu selesai di sana dan nilai palsunya tidak pernah dipakai.
     *
     * Karena itu test ini menangkap exception-nya, memeriksa nilai properti
     * sesudahnya, lalu memanggil `simpan()`. Kalau `#[Locked]` dihapus,
     * `set()` tidak melempar apa pun dan helper di bawah menggagalkan test.
     */
    public function test_halaman_terkunci_tidak_bisa_dibuka_kunci_dari_snapshot_klien(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        $component = Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ]);

        $this->buktikanTerkunci($component, 'bolehUbah', true);

        $this->assertFalse($component->get('bolehUbah'));

        $component->call('simpan')
            ->assertHasErrors('form')
            ->assertSee(self::PESAN_LAMPAU);

        $this->assertSame(0, Attendance::count());
    }

    /**
     * Bahaya `tanggal` lebih besar daripada `bolehUbah`. Flag yang dipalsukan
     * hanya menghasilkan pesan yang salah; tanggal yang dipalsukan menulis baris
     * `attendances` sungguhan dengan `date` yang berbeda dari URL halaman itu,
     * dan tidak ada yang menandainya.
     *
     * Test ini mengunci dua sisi sekaligus: snapshot tidak bisa mengubah
     * tanggalnya, dan setelah percobaan gagal itu tanggal yang dipakai tetap
     * tanggal asal — bukan hari ini.
     */
    public function test_tanggal_tidak_bisa_dipalsukan_dari_snapshot_klien(): void
    {
        $guru = User::factory()->guru()->create();
        $jadwal = $this->jadwalMilik($guru);
        $this->actingAs($guru);

        $component = Livewire::test(AttendanceEntry::class, [
            'schedule' => $jadwal,
            'tanggal_absensi' => today()->subDays(3)->toDateString(),
        ]);

        $this->buktikanTerkunci($component, 'tanggal', today()->toDateString());

        $this->assertSame(
            today()->subDays(3)->toDateString(),
            $component->get('tanggal')->toDateString(),
        );

        $component->call('simpan')
            ->assertHasErrors('form')
            ->assertSee(self::PESAN_LAMPAU);

        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, Attendance::whereDate('date', today()->toDateString())->count());
    }

    /**
     * Mencoba menulis properti terkunci dan membuktikan Livewire menolaknya.
     *
     * Exception-nya ditangkap, bukan expectation, karena yang penting nilai
     * sesudahnya, bukan kelas exception-nya. Kalau `#[Locked]` dihapus,
     * `set()` tidak melempar apa pun dan test gagal di sini — bukan lolos
     * diam-diam.
     */
    private function buktikanTerkunci(Testable $component, string $properti, mixed $nilai): void
    {
        try {
            $component->set($properti, $nilai);
        } catch (CannotUpdateLockedPropertyException $ditolak) {
            $this->assertSame($properti, $ditolak->property);

            return;
        }

        $this->fail("Properti terkunci [{$properti}] menerima nilai dari snapshot klien.");
    }

    private function kelasDenganDuaSiswa(): array
    {
        $kelas = Schedule::factory()->create()->schoolClass;

        foreach (range(1, 2) as $urutan) {
            Student::factory()->create([
                'class_id' => $kelas->id,
                'name' => 'Siswa '.$urutan,
            ]);
        }

        return [$kelas, Student::query()->where('class_id', $kelas->id)->pluck('id')];
    }
}
