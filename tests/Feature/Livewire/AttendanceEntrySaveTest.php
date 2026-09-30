<?php

namespace Tests\Feature\Livewire;

use App\Enums\AttendanceStatus;
use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
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
     * Karena `pastikanBarisLengkap()` menolak batch yang tidak menutup
     * roster, test ini kini membuktikan form menolak sebelum apa pun ditulis
     * — bukan lagi rollback transaksinya. Bukti rollback yang sebenarnya ada
     * di `AttendanceRecorderTest::test_kegagalan_satu_siswa_membatalkan_seluruh_batch`,
     * yang memanggil service langsung dan memeriksa `Attendance::count()`
     * tetap nol setelah baris pertama sempat ditulis.
     */
    public function test_satu_baris_gagal_membatalkan_seluruh_batch(): void
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
            ->set('status', [
                $ids->first() => 'hadir',
                $asing->id => 'hadir',
                $ids->last() => 'hadir',
            ])
            ->call('simpan')
            ->assertHasErrors('form');

        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->first()]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->last()]);
    }

    /**
     * Absensi parsial berarti siswa yang tidak terkirim tidak punya catatan
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

    /**
     * `record()` mengotorisasi setiap panggilan di dalam service, jadi guru
     * yang menggeser `tanggal` ke lima hari lalu lalu memanggil `simpan()`
     * mendarat di catch `AuthorizationException` dan berakhir sebagai pesan
     * `form` — bukan 403, bukan \TypeError, dan tidak ada yang tertulis.
     * Jalur ini sudah tertutup sejak Plan 1; yang dipindahkan di Task 6 hanya
     * posisi gate tanggalnya, bukan lubiknya.
     */
    public function test_guru_menolak_tanggal_di_luar_jendela_saat_menyimpan(): void
    {
        $guru = User::factory()->guru()->create();
        [$kelas, $ids] = $this->kelasDenganSiswa(2);
        $jadwal = Schedule::factory()->create([
            'teacher_id' => $guru->id,
            'class_id' => $kelas->id,
        ]);
        $this->actingAs($guru);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('tanggal', today()->subDays(5))
            ->call('simpan')
            ->assertHasErrors('form')
            ->assertSee('Kamu tidak berwenang mengisi absensi jadwal ini. Hubungi admin.')
            ->assertSet('sukses', false);

        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseMissing('attendances', ['student_id' => $ids->first()]);
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
