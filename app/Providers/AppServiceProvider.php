<?php

namespace App\Providers;

use App\Actions\AttemptAuthentication;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->registerPolicies();
        $this->registerLoginRateLimiter();
    }

    /**
     * UserPolicy deciding "hanya admin" ini yang jadi pagar sesungguhnya.
     * Filter role di dalam Blade atau di render() bukan pengganti policy,
     * karena Livewire action bisa dipanggil langsung dari browser.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
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
        Model::shouldBeStrict(! app()->isProduction());

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
