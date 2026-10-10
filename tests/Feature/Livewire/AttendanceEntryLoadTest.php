<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Support\IndonesianDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryLoadTest extends TestCase
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

    private function jadwalDenganSiswa(int $jumlahSiswa = 3): array
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
        ]);

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $jadwal->class_id,
                'name' => 'Siswa '.$urutan,
            ]);
        }

        $this->actingAs($guru);

        return [$guru, $jadwal];
    }

    public function test_tanggal_terkunci_ke_hari_ini(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Tanggal absensi dikunci')
            ->assertSee('Senin, 28 September 2026')
            ->assertSet('tanggal', today());
    }

    public function test_tidak_ada_input_tanggal_yang_bisa_diubah(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertDontSee('type="date"', false)
            ->assertDontSee('name="tanggal"', false);
    }

    public function test_mapel_diarsipkan_tetap_muncul_di_judul_absensi(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        $namaMapel = $jadwal->subject->name;

        // Mapel diarsipkan, tapi riwayat absensi jadwal ini tidak boleh hilang.
        $jadwal->subject->delete();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal->fresh()])
            ->assertOk()
            ->assertSee($namaMapel);
    }

    /**
     * Heading halaman harus menandai halaman apa yang sedang dibuka. Sebelumnya
     * satu-satunya `<h1>` berisi rentang jam ("07:00 - 08:30"), sedangkan
     * mapel dan kelas duduk di `<p>` kecil di atasnya — jadi dokumen tidak punya
     * heading yang mengidentifikasi absensi yang mana, dan pembaca layar
     * akan membacakan angka jam sebagai judul halaman.
     *
     * Assertion ini memeriksa heading sebagai satu kesatuan, bukan keberadaan
     * tiap potongan terpisah: memindahkan mapel kembali ke `<p>` masih
     * memenuhi `assertSee($jadwal->subject->name)`, jadi hanya teks heading
     * gabungan yang bisa membedakan kedua susunan itu.
     */
    public function test_judul_halaman_menyebut_mapel_dan_kelas(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSeeInOrder(
                [
                    '<h1',
                    'Absensi '.$jadwal->subject->name.' - Kelas '.$jadwal->schoolClass->name,
                    '</h1>',
                ],
                escape: false,
            );
    }

    public function test_menampilkan_jam_jadwal_besar_di_atas_form(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('07:00 - 08:30')
            ->assertSee($jadwal->subject->name)
            ->assertSee('Kelas '.$jadwal->schoolClass->name);
    }

    /**
     * mount() mencocokkan parameter rute berdasarkan nama. Kalau nama parameter
     * berubah, tanggal yang diminta datang sebagai null dan halaman diam-diam
     * menampilkan hari ini — lebih buruk lagi, `recordAttendance` ikut mengetatkan
     * jadwal ke hari ini sehingga jendela rekam absensi ikut terbalik. Karena itu
     * diuji lewat rute sungguhan dengan tanggal yang benar-benar berbeda dari
     * hari ini: `assertOk()` saja lolos pada apa pun yang jatuh ke fallback.
     */
    public function test_tanggal_dari_rute_sampai_ke_halaman(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();
        $tanggalDiminta = today()->subDay();

        $this->get(route('absensi.show', [$jadwal, $tanggalDiminta->toDateString()]))
            ->assertOk()
            ->assertSee(IndonesianDate::tanggal($tanggalDiminta))
            ->assertDontSee(IndonesianDate::tanggal(today()));
    }

    /**
     * `hasFormat('Y-m-d')` hanya mengecek bentuk, bukan kalender: `2026-02-30`
     * lolos lalu digulirkan `Carbon::parse` menjadi 2 Maret tanpa error. Dua
     *-duanya harus 404 supaya tidak ada tanggal liar yang sampai ke query absensi.
     */
    public function test_tanggal_liar_dari_rute_menghasilkan_404(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();

        $this->get(route('absensi.show', [$jadwal, 'bukan-tanggal']))->assertNotFound();
        $this->get(route('absensi.show', [$jadwal, '2026-02-30']))->assertNotFound();
    }

    public function test_semua_siswa_default_hadir_dan_terurut_nama(): void
    {
        [, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()
            ->where('class_id', $jadwal->class_id)
            ->orderBy('name')
            ->pluck('id');

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSeeInOrder(['Siswa 1', 'Siswa 2', 'Siswa 3']);

        foreach ($siswa as $id) {
            $component->assertSet('status.'.$id, 'hadir');
        }
    }

    public function test_absensi_lama_dimuat_bukan_form_kosong(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Sakit,
            'note' => 'Demam sejak pagi',
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSet('sudahDisimpan', true)
            ->assertSet('status.'.$siswa->id, 'sakit')
            ->assertSet('note.'.$siswa->id, 'Demam sejak pagi')
            ->assertSee('value="sakit" selected', false);
    }

    public function test_tombol_menjadi_simpan_perubahan_setelah_absensi_ada(): void
    {
        [$guru, $jadwal] = $this->jadwalDenganSiswa();
        $siswa = Student::query()->where('class_id', $jadwal->class_id)->firstOrFail();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Absensi')
            ->assertDontSee('Simpan Perubahan');

        Attendance::factory()->create([
            'student_id' => $siswa->id,
            'schedule_id' => $jadwal->id,
            'date' => today(),
            'status' => AttendanceStatus::Hadir,
            'recorded_by' => $guru->id,
        ]);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertOk()
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('Simpan Absensi');
    }
}
