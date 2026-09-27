<?php

namespace App\Exceptions;

use RuntimeException;

class LastActiveAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Admin aktif terakhir tidak bisa dinonaktifkan atau dihapus. Sistem harus selalu punya minimal satu admin aktif.'
        );
    }
}
