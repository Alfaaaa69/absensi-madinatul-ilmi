<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttemptAuthentication
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /**
     * Coba login dengan kredensial dari form.
     *
     * Sengaja bukan Illuminate\Foundation\Auth\AuthenticatesUsers, karena
     * kelas itu mengasumsikan identitas login adalah email dan tidak punya
     * tempat untuk pesan "akun dinonaktifkan".
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials): void
    {
        $identifier = (string) ($credentials['email'] ?? $credentials['nuptk'] ?? '');
        $field = isset($credentials['email']) ? 'email' : 'nuptk';

        $key = self::throttleKeyFor($identifier, request()->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                $field => [__('auth.throttle', ['seconds' => $seconds])],
            ]);
        }

        $authCredentials = [
            $field => $identifier,
            'password' => $credentials['password'],
        ];

        $authenticated = Auth::attempt($authCredentials, (bool) ($credentials['remember'] ?? false));

        if (! $authenticated) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            $this->fail(__('auth.failed'), $field);
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            Auth::logout();

            $this->fail(__('auth.failed'), $field);
        }

        if (! $user->is_active) {
            Auth::logout();

            $this->fail('Akun ini sudah dinonaktifkan. Hubungi admin.', $field);
        }

        RateLimiter::clear($key);
    }

    /**
     * Bangun kunci throttle dari email/nuptk dan IP.
     *
     * Dipakai dua kali: oleh attempt() di bawah, dan oleh named limiter
     * 'login' di AppServiceProvider. Satu implementasi supaya kedua jalur
     * tidak pernah menghitung kunci yang berbeda.
     */
    public static function throttleKeyFor(string $identifier, ?string $ip): string
    {
        return 'login|'.Str::lower($identifier).'|'.(string) $ip;
    }

    private function fail(string $message, string $field = 'email'): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
