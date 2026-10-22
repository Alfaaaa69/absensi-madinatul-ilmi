<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password dari admin (akun baru atau hasil reset) hanya berlaku sementara.
 * Middleware ini memaksa pengguna menggantinya sebelum membuka halaman data.
 *
 * shortcut: hanya request halaman yang dicek. Halaman Livewire yang sudah
 * terbuka sebelum admin mereset password masih bisa dipakai sampai reload;
 * upgrade ke pengecekan per-aksi kalau itu jadi risiko nyata.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs('password.change')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
