<?php

namespace App\Imports;

use App\Enums\StudentGender;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

final class StudentRowValidator
{
    /**
     * @param  array<int, array<array-key, mixed>>  $rows
     * @param  Collection<int, SchoolClass>  $kelas
     * @return array<int, array<string, mixed>>
     */
    public function validate(array $rows, Collection $kelas): array
    {
        $nisnDiDatabase = Student::withTrashed()->pluck('nisn')->map(fn ($nisn) => (string) $nisn)->all();

        /** @var array<string, SchoolClass> $kelasPerNama */
        $kelasPerNama = [];

        foreach ($kelas as $item) {
            $kelasPerNama[mb_strtoupper($item->name)] = $item;
        }

        $hasil = [];
        $nisnDiFileIni = [];

        foreach ($rows as $index => $row) {
            $nomor = $index + 2;

            $nisn = $this->nilai($row, ['nisn']);
            $nama = $this->nilai($row, ['nama', 'nama lengkap', 'name']);
            $jk = $this->nilai($row, ['jk', 'jenis kelamin', 'gender', 'lp']);
            $namaKelas = $this->nilai($row, ['kelas', 'class']);

            $galat = [];

            if ($nisn === '') {
                $galat[] = 'NISN wajib diisi.';
            } elseif (in_array($nisn, $nisnDiDatabase, true)) {
                $galat[] = "NISN {$nisn} sudah terdaftar, termasuk yang diarsipkan.";
            } elseif (isset($nisnDiFileIni[$nisn])) {
                $galat[] = "NISN {$nisn} juga dipakai pada baris {$nisnDiFileIni[$nisn]}.";
            }

            if ($nama === '') {
                $galat[] = 'Nama wajib diisi.';
            }

            $gender = $this->gender($jk);
            $labelJk = $jk;

            if ($gender === null) {
                $galat[] = "Jenis kelamin '{$jk}' tidak dikenal. Gunakan L atau P.";
            } else {
                $labelJk = $gender->value;
            }

            $kelasTerpilih = null;
            $labelKelas = $namaKelas;

            if ($namaKelas === '') {
                $galat[] = 'Kelas wajib diisi.';
            } else {
                $kelasTerpilih = $kelasPerNama[mb_strtoupper($namaKelas)] ?? null;

                if ($kelasTerpilih === null) {
                    $galat[] = "Kelas '{$namaKelas}' tidak ditemukan pada tahun ajaran terpilih.";
                } else {
                    $labelKelas = $kelasTerpilih->name;
                }
            }

            $hasil[] = [
                'row' => $nomor,
                'nisn' => $nisn,
                'nama' => $nama,
                'gender' => $labelJk,
                'kelas' => $labelKelas,
                'class_id' => $kelasTerpilih?->id,
                'valid' => $galat === [],
                'errors' => $galat,
            ];

            if ($nisn !== '' && ! in_array($nisn, $nisnDiDatabase, true)) {
                $nisnDiFileIni[$nisn] = $nomor;
            }
        }

        return $hasil;
    }

    /**
     * Kunci baris sudah di-slug oleh `imports.heading_row.formatter` di
     * `config/excel.php`, jadi `Nama Lengkap` menjadi `nama_lengkap`. Underscore
     * dinormalkan ke spasi supaya daftar alias di bawah bisa memakai spasi dan
     * tetap cocok apa pun formater yang dipakai.
     *
     * @param  array<array-key, mixed>  $row
     * @param  array<int, string>  $kunci
     */
    private function nilai(array $row, array $kunci): string
    {
        foreach ($row as $kolom => $isi) {
            $nama = str_replace('_', ' ', mb_strtolower(trim((string) $kolom)));

            if (in_array($nama, $kunci, true)) {
                return trim((string) $isi);
            }
        }

        return '';
    }

    private function gender(string $isi): ?StudentGender
    {
        return match (mb_strtolower($isi)) {
            'l', 'lk', 'pri', 'laki-laki', 'laki laki' => StudentGender::L,
            'p', 'pr', 'perempuan' => StudentGender::P,
            default => null,
        };
    }
}
