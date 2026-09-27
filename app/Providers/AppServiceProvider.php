<?php

namespace App\Providers;

use App\Actions\AttemptAuthentication;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerLoginRateLimiter();
    }

    /**
     * Daftarkan named limiter 'login' yang dipakai form login.
     *
     * Kunci throttle sengaja dibangun dari nuptk + IP lewat
     * AttemptAuthentication::throttleKeyFor(), sama persis dengan yang
     * dipakai di dalam attempt(). Dua jalur ini harus menghitung kunci
     * yang identik, kalau tidak batas percobaan bisa dilewati.
     */
    protected function registerLoginRateLimiter(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $nuptk = (string) $request->input('nuptk');

            return new Limit(
                key: AttemptAuthentication::throttleKeyFor($nuptk, $request->ip()),
                maxAttempts: AttemptAuthentication::MAX_ATTEMPTS,
                decaySeconds: AttemptAuthentication::DECAY_SECONDS,
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
