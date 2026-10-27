<?php

namespace App\Attendance\Queries;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Akun admin aktif, ditampilkan sebagai tujuan "Hubungi admin" ketika
 * absensi tidak bisa diubah. Syarat role tinggal di sini, bukan di view.
 */
final class AdminContactsQuery
{
    /**
     * @return Collection<int,array{nama:string,email:?string}>
     */
    public function active(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'email'])
            ->map(fn (User $admin) => [
                'nama' => $admin->name,
                'email' => $admin->email,
            ]);
    }
}
