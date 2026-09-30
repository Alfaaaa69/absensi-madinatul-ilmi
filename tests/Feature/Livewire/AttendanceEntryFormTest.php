<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AttendanceEntry;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceEntryFormTest extends TestCase
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

    private function formSiap(int $jumlahSiswa = 3): array
    {
        $guru = User::factory()->guru()->create();
        $jadwal = Schedule::factory()->create(['teacher_id' => $guru->id]);

        foreach (range(1, $jumlahSiswa) as $urutan) {
            Student::factory()->create([
                'class_id' => $jadwal->class_id,
                'name' => 'Siswa '.$urutan,
            ]);
        }

        $this->actingAs($guru);

        return [$jadwal, Student::query()->where('class_id', $jadwal->class_id)->pluck('id')];
    }

    public function test_tandai_semua_hadir_mengisi_seluruh_baris(): void
    {
        [$jadwal, $ids] = $this->formSiap();
        $pertama = $ids->first();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('note.'.$pertama, 'Demam sejak kemarin')
            ->call('tandaiSemua', 'izin')
            ->assertSet('note.'.$pertama, null);

        foreach ($ids as $id) {
            $component->assertSet('status.'.$id, 'izin');
        }

        // Alasan diisi ulang sebelum ditandai hadir karena aksi massal menulis
        // status lewat assignment langsung, bukan lewat `wire:model` —
        // `updatedStatus` tidak menyala di jalur ini, jadi pembersihannya
        // harus datang dari `$this->note = []` di dalam tandaiSemua.
        $component->set('note.'.$pertama, 'Demam lagi')
            ->call('tandaiSemua', 'hadir')
            ->assertSet('note.'.$pertama, null);

        foreach ($ids as $id) {
            $component->assertSet('status.'.$id, 'hadir');
        }
    }

    public function test_tandai_semua_alpa_mengisi_seluruh_baris(): void
    {
        [$jadwal, $ids] = $this->formSiap();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'alpa');

        foreach ($ids as $id) {
            $component->assertSet('status.'.$id, 'alpa');
        }
    }

    /**
     * `tandaiSemua` adalah action publik Livewire, jadi argumennya datang dari
     * snapshot klien, bukan hanya dari dua nilai yang ditulis di `wire:click`.
     * Status yang tidak dikenal harus diabaikan, bukan dilempar keluar sebagai
     * `\ValueError` — kalau lempar, satu permintaan yang direkayasa membuat
     * halaman 500.
     */
    public function test_tandai_semua_dengan_status_tidak_dikenal_diabaikan(): void
    {
        [$jadwal, $ids] = $this->formSiap(2);

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'izin')
            ->call('tandaiSemua', 'bolos')
            ->assertOk();

        foreach ($ids as $id) {
            $component->assertSet('status.'.$id, 'izin');
        }
    }

    public function test_kolom_alasan_muncul_untuk_izin_sakit_dan_alpa(): void
    {
        [$jadwal, $ids] = $this->formSiap(1);
        $id = $ids->first();

        $component = Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');

        foreach (['izin', 'sakit', 'alpa'] as $status) {
            $component->set('status.'.$id, $status)
                ->assertSee('Contoh: Demam, acara keluarga, dll');
        }

        $component->set('status.'.$id, 'hadir')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll');
    }

    /**
     * Dua hal harus terjadi saat guru mengembalikan status ke hadir: kolom
     * alasan hilang dari layar, dan isi alasan yang sudah diketik ikut dibuang.
     * Yang kedua hanya bisa diuji lewat state — `alasanTerlihat()` membaca
     * `$this->status`, bukan `$this->note`, jadi assertion placeholder hilang
     * tetap hijau walau pembersihannya dihapus. Tanpa state yang benar, `simpan()`
     * pada tahap berikutnya akan menulis alasan sisa ke baris `hadir`.
     */
    public function test_kolom_alasan_dan_isi_alasannya_hilang_saat_status_kembali_hadir(): void
    {
        [$jadwal, $ids] = $this->formSiap(1);
        $id = $ids->first();

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->set('status.'.$id, 'sakit')
            ->assertSee('Contoh: Demam, acara keluarga, dll')
            ->set('note.'.$id, 'Demam sejak kemarin')
            ->set('status.'.$id, 'hadir')
            ->assertDontSee('Contoh: Demam, acara keluarga, dll')
            ->assertSet('note.'.$id, null);
    }

    public function test_tidak_ada_autosave_saat_status_diubah(): void
    {
        [$jadwal, $ids] = $this->formSiap(2);

        Livewire::test(AttendanceEntry::class, ['schedule' => $jadwal])
            ->call('tandaiSemua', 'alpa')
            ->set('note.'.$ids->first(), 'Tidak masuk tanpa keterangan')
            ->assertOk();

        $this->assertSame(0, Attendance::count());
    }
}
