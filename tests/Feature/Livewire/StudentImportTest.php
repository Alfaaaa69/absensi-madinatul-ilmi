<?php

namespace Tests\Feature\Livewire;

use App\Livewire\StudentImport;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\StudentRowsSheet;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * File `.xlsx` asli, supaya yang diuji benar-benar parsing Excel dan bukan
     * mock. `Illuminate\Http\Testing\File` dipakai karena `Livewire::test()`
     * membaca properti publik `$name` saat menyimulasikan unggahan; `File`
     * bawaan factory hanya berisi nol dan tidak bisa diisi byte xlsx.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function xlsx(array $rows): File
    {
        return File::createWithContent(
            'data-siswa.xlsx',
            Excel::raw(new StudentRowsSheet($rows), ExcelType::XLSX)
        );
    }

    /**
     * @return array<int, string>
     */
    private function header(): array
    {
        return ['NISN', 'Nama', 'JK', 'Kelas'];
    }

    public function test_file_valid_mengimpor_seluruh_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000002', 'Sari Wulandari', 'P', 'VII-A'],
        ]);

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->assertHasNoErrors()
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame(2, Student::count());
        $this->assertDatabaseHas('students', ['nisn' => '3200000001', 'gender' => 'L', 'name' => 'Budi Santoso']);
        $this->assertDatabaseHas('students', ['nisn' => '3200000002', 'gender' => 'P', 'name' => 'Sari Wulandari']);
    }

    public function test_baris_tidak_valid_menampilkan_alasan_dan_tidak_menyimpan_apa_pun(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['', 'Tanpa NISN', 'L', 'VII-A'],
            ['3200000003', 'Kelas Salah', 'L', 'VII-Z'],
            ['3200000004', 'Gender Salah', 'X', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->assertHasNoErrors()
            ->assertSee('NISN wajib diisi.');

        $barisGagal = $komponen->instance()->barisGagal();

        $this->assertSame(3, count($barisGagal));
        $this->assertSame(1, count($komponen->instance()->barisValid()));
        $this->assertStringContainsString(
            "Kelas 'VII-Z' tidak ditemukan",
            implode(' ', array_merge(...array_column($barisGagal, 'errors')))
        );
        $this->assertStringContainsString(
            "Jenis kelamin 'X' tidak dikenal",
            implode(' ', array_merge(...array_column($barisGagal, 'errors')))
        );

        $komponen->call('confirm')->assertHasErrors(['file']);

        $this->assertSame(0, Student::count());
    }

    public function test_file_sebagian_valid_ditolak_tidak_disimpan_sebagian(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000002', 'Sari Wulandari', 'L', 'Kelas Tidak Ada'],
        ]);

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->call('confirm')
            ->assertHasErrors(['file']);

        $this->assertSame(0, Student::count());
    }

    public function test_nisn_kembar_di_dalam_file_yang_sama_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
            ['3200000001', 'Budi Santoso Dua', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview');

        $barisGagal = $komponen->instance()->barisGagal();

        $this->assertCount(1, $barisGagal);
        $this->assertSame(3, $barisGagal[0]['row']);
        $this->assertStringContainsString('3200000001', $barisGagal[0]['errors'][0]);
        $this->assertSame(0, Student::count());
    }

    public function test_nisn_yang_sudah_terdaftar_ditolak(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);
        Student::factory()->create(['class_id' => $kelas->id, 'nisn' => '3200000001']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000001', 'Budi Santoso', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview');

        $this->assertStringContainsString(
            'sudah terdaftar',
            $komponen->instance()->barisGagal()[0]['errors'][0]
        );
    }

    public function test_nisn_siswa_arsip_tidak_bisa_dipakai_ulang(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        $kelas = SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-A']);
        $siswa = Student::factory()->create(['class_id' => $kelas->id, 'nisn' => '3200000005']);
        $siswa->delete();

        $file = $this->xlsx([
            $this->header(),
            ['3200000005', 'Budi Santoso', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview');

        $this->assertStringContainsString(
            'sudah terdaftar',
            $komponen->instance()->barisGagal()[0]['errors'][0]
        );
    }

    public function test_ejaan_kolom_dan_jenis_kelamin_berupa_kata_diterima(): void
    {
        $admin = User::factory()->admin()->create();
        $tahun = AcademicYear::factory()->create();
        SchoolClass::factory()->create(['academic_year_id' => $tahun->id, 'name' => 'VII-B']);

        $file = $this->xlsx([
            ['NISN', 'Nama Lengkap', 'Jenis Kelamin', 'Kelas'],
            ['3200000009', 'Andi Pratama', 'Laki-laki', 'VII-B'],
            ['3200000010', 'Siti Aminah', 'Perempuan', 'vii-b'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahun->id)
            ->set('file', $file)
            ->call('preview')
            ->assertHasNoErrors();

        $this->assertSame(0, count($komponen->instance()->barisGagal()));
        $this->assertSame(2, count($komponen->instance()->barisValid()));

        $komponen->call('confirm')->assertHasNoErrors();

        $this->assertSame(2, Student::count());
        $this->assertDatabaseHas('students', ['nisn' => '3200000009', 'gender' => 'L', 'name' => 'Andi Pratama']);
        $this->assertDatabaseHas('students', ['nisn' => '3200000010', 'gender' => 'P', 'name' => 'Siti Aminah']);
    }

    public function test_kelas_tahun_lain_tidak_bisa_dipakai(): void
    {
        $admin = User::factory()->admin()->create();
        $tahunLain = AcademicYear::factory()->create();
        $tahunDipilih = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);
        SchoolClass::factory()->create(['academic_year_id' => $tahunLain->id, 'name' => 'VII-A']);

        $file = $this->xlsx([
            $this->header(),
            ['3200000011', 'Budi Santoso', 'L', 'VII-A'],
        ]);

        $komponen = Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->set('academic_year_id', $tahunDipilih->id)
            ->set('file', $file)
            ->call('preview');

        $this->assertSame(1, count($komponen->instance()->barisGagal()));
        $this->assertStringContainsString(
            'tidak ditemukan',
            $komponen->instance()->barisGagal()[0]['errors'][0]
        );
    }

    public function test_tahun_ajaran_dan_file_wajib_diisi(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StudentImport::class)
            ->call('preview')
            ->assertHasErrors(['academic_year_id', 'file']);

        $this->assertSame(0, Student::count());
    }

    public function test_guru_tidak_bisa_membuka_halaman_impor(): void
    {
        $guru = User::factory()->guru()->create(['is_active' => true]);

        $this->actingAs($guru)->get('/master/impor-siswa')->assertForbidden();
    }

    public function test_admin_bisa_membuka_halaman_impor(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get('/master/impor-siswa')
            ->assertOk()
            ->assertSee('Impor Siswa');
    }
}
