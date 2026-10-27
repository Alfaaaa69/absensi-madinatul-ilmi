<?php

namespace App\Enums;

enum HasilHapus: string
{
    /**
     * Baris benar-benar hilang dari database dan tidak bisa dipulihkan lagi.
     */
    case Permanen = 'permanen';

    /**
     * Baris tetap ada, hanya ditandai deleted_at sehingga masih bisa
     * dipulihkan lewat daftar arsip.
     */
    case Diarsipkan = 'diarsipkan';
}
