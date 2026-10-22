<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Tutup kasus yang tidak bisa ditutup AttemptAuthentication: akun aktif
     * waktu login, lalu dinonaktifkan admin sementara session masih hidup.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun ini sudah dinonaktifkan. Hubungi admin.',
                    'nuptk' => 'Akun ini sudah dinonaktifkan. Hubungi admin.',
                ]);
        }

        return $next($request);
    }
}
