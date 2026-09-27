<?php

namespace Tests\Support;

use Maatwebsite\Excel\Concerns\FromArray;

final class StudentRowsSheet implements FromArray
{
    /**
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
