<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntrySaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-28', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function kelasDenganSiswa(int $jumlahSiswa = 3): array
    {
        $kelas = Schedule::factory()->create()->schoolClass;

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $kelas->id,
                'name' => 'Siswa '.$urutan,
            ]);
        }

        return [$kelas, Student::query()->where('class_id', $kelas->id)->pluck('id')];
    }

    public function test_simpan_menulis_semua_baris_absensi(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.'.$ids->last(), 'sakit')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('sukses', true)
            ->assertSee('Simpan Perubahan');

        $this->assertSame(3, Attendance::count());

        foreach ($ids as $id) {
            $baris = Attendance::where('student_id', $id)->firstOrFail();

            $this->assertSame($guru->id, $baris->recorded_by);
            $this->assertNull($baris->updated_by);
            $this->assertSame(today()->toDateString(), $baris->date->toDateString());
        }

        $this->assertSame(
            AttendanceStatus::Sakit,
            Attendance::where('student_id', $ids->last())->firstOrFail()->status,
        );
    }

    public function test_submit_dua_kali_tidak_menggandakan_baris(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa();
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $admin = User::factory()->admin()->create();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('simpan')
            ->assertHasNoErrors();

        $this->actingAs($admin);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.'.$ids->first(), 'alpa')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(3, Attendance::count());

        $baris = Attendance::where('student_id', $ids->first())->firstOrFail();

        $this->assertSame(AttendanceStatus::Alpa, $baris->status);
        $this->assertSame($guru->id, $baris->recorded_by);
        $this->assertSame($admin->id, $baris->updated_by);
    }

    public function test_admin_boleh_menyimpan_tanggal_lampau(): void
    {
        $admin = User::factory()->admin()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => User::factory()->guru()->create()->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($admin);
        $lampu = today()->subDays(5)->toDateString();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal, 'tanggal_absensi' => $lampu])
            ->assertOk()
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(2, Attendance::whereDate('date', $lampu)->count());
        $this->assertSame(2, $ids->count());
    }

    /**
     * Baris asing tidak pernah sampai ke `AttendanceRecorder` lewat form:
     * `pastikanBarisLengkap()` menolak lebih dulu karena kunci yang dikirim
     * tidak sama dengan roster yang dimuat `mount()`. Penjaga keanggotaan
     * kelas milik service tetap ada sebagai lapisan kedua dan diuji langsung
     * di `AttendanceRecorderTest::test_siswa_dari_kelas_lain_ditolak`.
     */
    public function test_siswa_dari_kelas_lain_ditolak(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $asing = Student::factory()->create();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status', [$asing->id => 'hadir'])
            ->call('simpan')
            ->assertHasErrors('form');

        $this->assertSame(0, Attendance::count());
        $this->assertSame(2, $ids->count());
    }

    /**
     * Rollback yang terbukti di sini bukan "tidak ada yang sempat ditulis",
     * melainkan "satu baris sempat ditulis lalu dibatalkan".
     *
     * `pastikanBarisLengkap()` membandingkan kunci `status` dengan `baris`
     * yang dimuat `mount()`, dan `mount()` tidak berjalan ulang, jadi kelas
     * yang dipakai untuk menyusun roster adalah kelas saat halaman dibuka.
     * Menggeser satu siswa ke kelas lain lewat model — bukan lewat form —
     * membuat roster yang dihitung penjaga tetap utuh, penjaga lolos, dan
     * `AttendanceRecorder` yang menolak, karena ia membaca keanggotaan kelas
     * dari database di dalam `record()`.
     *
     * Siswa yang digeser dipilih yang paling akhir di form supaya baris
     * pertama benar-benar lebih dulu masuk ke transaksi. Kalau tidak begitu,
     * `Attendance::count() === 0` hanya membuktikan tidak ada yang sempat
     * ditulis, bukan bahwa rollback bekerja.
     *
     * Syarat "paling akhir" itu adalah ketergantungan urutan yang tidak
     * terlihat dari test ini: form diurutkan oleh `mount()` lewat
     * `orderBy('name')`, dan `barisAbsensi()` lalu iterate
     * `array_keys($this->status)`, yang terisi dari loop yang sama. Keduanya
     * masih seurut selama
     * `mount()` mengisi `$baris` dan `$status` dalam satu loop; kalau itu
     * berubah, `Attendance::count() === 0` akan pelan-pelan berubah artinya
     * menjadi "tidak ada yang ditulis" — bacaan yang justru paling ingin
     * dihindari — dan test ini tetap hijau. Karena itu urutan itu diperiksa
     * langsung di bawah, bukan cuma diasumsikan.
     */
    public function test_satu_baris_gagal_membatalkan_seluruh_batch(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $kelasLain = SchoolClass::factory()->create();
        $this->actingAs($guru);

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal]);

        $urutan = array_keys($component->get('baris'));
        $terulisDulu = $urutan[0];
        $pindah = $urutan[1];

        // Yang dijaga di sini bukan hanya "sisanya ditolak", tapi bahwa yang
        // ditolak adalah baris paling akhir. Kalau urutannya berubah, baris
        // pertama yang gagal dan test ini mulai hijau tanpa memeriksa apa pun.
        $this->assertSame(
            array_keys($component->get('status')),
            $urutan,
            'Urutan baris form dan status harus sama; test ini bergantung pada itu.',
        );

        Student::query()->whereKey($pindah)->update(['class_id' => $kelasLain->id]);

        $component->call('simpan')
            ->assertHasErrors(['student_id', 'form'])
            ->assertSee('Absensi gagal disimpan. Tidak ada satu pun data yang tersimpan.')
            ->assertSet('sukses', false);

        $this->assertSame(2, $ids->count());
        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseMissing('attendances', ['student_id' => $terulisDulu]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $pindah]);
    }

    /**
     * Batch parsial berarti siswa yang tidak terkirim tidak punya catatan
     * sama sekali, padahal guru tetap melihat "Absensi tersimpan". Pemulihan
     * di halaman berikutnya bukan perbaikan netral: `mount()` mengisi baris
     * tanpa data dengan `hadir`, jadi pada simpanan guru berikutnya siswa
     * yang berlubang berubah diam-diam dari "tidak tercatat" menjadi "hadir",
     * dan rekap menghitungnya hadir. Karena itu batch harus menutup seluruh
     * roster sebelum service dipanggil, dan tidak boleh ada satu baris pun
     * yang tertulis — termasuk baris siswa yang memang dikirim.
     */
    public function test_siswa_yang_tidak_dikirim_menolak_simpan_sepenuhnya(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status', [$ids->first() => 'hadir'])
            ->call('simpan')
            ->assertHasErrors('form')
            ->assertSee('Daftar siswa di form ini tidak lengkap.')
            ->assertSet('sukses', false);

        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->first()]);
    }

    // `test_guru_menolak_tanggal_di_luar_jendela_saat_menyimpan` dihapus di
    // Task 6. Test itu memalsukan `tanggal` lewat snapshot klien, persis yang
    // `#[Locked]` sekarang larang, jadi ia mendokumentasikan celah yang sudah
    // tidak ada. Hasil yang dilihat guru dari kasus itu — pesan `form`
    // berbahasa manusia dan tidak ada yang tertulis — sekarang dibuktikan di
    // `AttendanceEntryWindowTest::test_simpan_di_halaman_terkunci_menolak_dengan_pesan_jendela`.
    //
    // Konsekuensi yang perlu diketahui: catch `AuthorizationException` di
    // `simpan()` tidak lagi punya test di level component. `simpan()` memanggil
    // `Gate::allows('recordAttendance', …)` dengan policy yang sama sebelum
    // menyentuh `record()`, jadi `record()` hanya menolak lewat
    // `AuthorizationException` kalau jam bergerak di antara dua pemanggilan itu.
    // Cabangnya tetap dipertahankan sebagai lapisan kedua, tapi tidak lagi
    // bisa direproduksi dari test.

    /**
     * Lampu hijau harus menggambarkan keadaan simpanan terakhir, bukan
     * hanya bahwa pernah ada simpanan berhasil. Kalau `sukses` tidak
     * dimatikan ulang di awal `simpan()`, guru yang gagal menyimpan tetap
     * melihat "Absensi tersimpan" di atas lembar yang isinya tidak berubah
     * sama sekali — dan itu persis pesan yang paling berbahaya di halaman
     * ini.
     *
     * Test harus memakai satu instance component yang sama: menguji reset
     * butuh urutan berhasil-lalu-gagal, jadi dua `Livewire::test` terpisah
     * tidak pernah bisa mengujinya, dan assertion `sukses === false` di jalur
     * gagal lain tidak pernah menyentuh baris `sukses = false` itu.
     */
    public function test_simpan_gagal_setelah_simpan_berhasil_mematikan_lampu_sukses(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $kelasLain = SchoolClass::factory()->create();
        $this->actingAs($guru);

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('sukses', true);

        $this->assertSame(2, Attendance::count());

        $urutan = array_keys($component->get('baris'));
        $diperbarui = $urutan[0];
        $pindah = $urutan[1];

        Student::query()->whereKey($pindah)->update(['class_id' => $kelasLain->id]);

        $component->set('status.'.$diperbarui, 'izin')
            ->call('simpan')
            ->assertHasErrors('form')
            ->assertSet('sukses', false);

        $this->assertSame(2, Attendance::count());
        $this->assertSame(
            AttendanceStatus::Hadir,
            Attendance::where('student_id', $diperbarui)->firstOrFail()->status,
        );
    }

    public function test_alasan_dibersihkan_saat_status_kembali_hadir(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(1);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $id = $ids->first();
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.'.$id, 'izin')
            ->set('note.'.$id, 'Acara keluarga')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Acara keluarga', Attendance::where('student_id', $id)->firstOrFail()->note);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.'.$id, 'izin')
            ->set('note.'.$id, 'Acara keluarga')
            ->set('status.'.$id, 'hadir')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertNull(Attendance::where('student_id', $id)->firstOrFail()->note);
    }
}
