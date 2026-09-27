<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const A = '01900000-0000-7000-8000-00000000000a';

    private const B = '01900000-0000-7000-8000-00000000000b';

    private function seedSupportingRows(string $id): void
    {
        DB::table('academic_years')->insert([
            'id' => $id, 'name' => '2025/2026', 'period' => 'ganjil',
            'start_date' => '2025-07-01', 'end_date' => '2025-12-31',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => $id, 'name' => 'Guru', 'nuptk' => '123456789012345678',
            'password' => bcrypt('rahasia'), 'role' => 'guru', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('subjects')->insert([
            'id' => $id, 'name' => 'Matematika', 'code' => 'MTK',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('classes')->insert([
            'id' => $id, 'academic_year_id' => $id, 'name' => 'VII-A',
            'grade_level' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('schedules')->insert([
            'id' => $id, 'class_id' => $id, 'subject_id' => $id, 'teacher_id' => $id,
            'day_of_week' => 1, 'start_time' => '07:00:00', 'end_time' => '08:30:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('students')->insert([
            'id' => $id, 'class_id' => $id, 'nisn' => '1001', 'name' => 'Budi',
            'gender' => 'L', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_tujuh_tabel_sudah_ada(): void
    {
        foreach ([
            'users', 'academic_years', 'classes', 'subjects',
            'schedules', 'students', 'attendances',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} belum ada");
        }
    }

    public function test_tidak_ada_tabel_bawaan_yang_tidak_dibutuhkan(): void
    {
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_attendances_unik_per_siswa_jadwal_tanggal(): void
    {
        $this->seedSupportingRows(self::A);

        $row = [
            'id' => self::A, 'student_id' => self::A, 'schedule_id' => self::A,
            'date' => '2025-09-01', 'status' => 'hadir', 'recorded_by' => self::A,
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('attendances')->insert($row);

        // id harus BERUBAH supaya yang dilanggar adalah unique (student_id, schedule_id,
        // date), bukan primary key. Pakai array_merge, bukan `$row + [...]`:
        // operator `+` mempertahankan nilai sisi kiri untuk key yang sama.
        $this->expectException(QueryException::class);
        DB::table('attendances')->insert(array_merge($row, ['id' => self::B]));
    }

    public function test_attendances_tidak_pakai_soft_delete(): void
    {
        $this->assertFalse(Schema::hasColumn('attendances', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('academic_years', 'deleted_at'));
    }

    public function test_tabel_master_pakai_soft_delete(): void
    {
        foreach (['users', 'students', 'classes', 'subjects', 'schedules'] as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'deleted_at'),
                "Tabel {$table} seharusnya punya soft delete"
            );
        }
    }

    public function test_homeroom_teacher_id_bisa_kosong(): void
    {
        $this->seedSupportingRows(self::A);

        DB::table('classes')->insert([
            'id' => self::B, 'academic_year_id' => self::A, 'homeroom_teacher_id' => null,
            'name' => 'VII-KOSONG', 'grade_level' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNull(
            DB::table('classes')->where('id', self::B)->value('homeroom_teacher_id')
        );
    }

    public function test_unik_kelas_per_tahun_ajaran(): void
    {
        $this->seedSupportingRows(self::A);

        $this->expectException(QueryException::class);

        DB::table('classes')->insert([
            'id' => self::B, 'academic_year_id' => self::A, 'name' => 'VII-A',
            'grade_level' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_unik_nisn_siswa(): void
    {
        $this->seedSupportingRows(self::A);

        $this->expectException(QueryException::class);

        DB::table('students')->insert([
            'id' => self::B, 'class_id' => self::A, 'nisn' => '1001', 'name' => 'Sari',
            'gender' => 'P', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
