<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

final class StudentSheetReader implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<array-key, mixed>> */
    private array $rows = [];

    /**
     * Reader Laravel Excel mengirim tiap baris sebagai `Collection`, bukan array.
     * `(array) $baris` pada objek Collection menghasilkan properti privatnya
     * (`items`, `escapeWhenCastingToString`), bukan pasangan kolom, jadi barisnya
     * harus diambil lewat `all()`.
     *
     * Dipanggil sekali per chunk oleh reader, jadi setiap potongan ditumpuk,
     * bukan ditimpa.
     *
     * @param  Collection<array-key, mixed>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[] = $row instanceof Collection
                ? $row->all()
                : (is_array($row) ? $row : []);
        }
    }

    /**
     * @return array<int, array<array-key, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
