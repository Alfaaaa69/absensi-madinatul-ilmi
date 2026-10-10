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
use Illuminate\Support\Facades\View;
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
        $this->registerRecapTabs();
    }

    /**
     * UserPolicy deciding "hanya admin" ini yang jadi pagar sesungguhnya.
     * Filter role di dalam Blade atau di render() bukan pengganti policy,
     * karena Livewire action bisa dipanggil langsung dari browser.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('viewAnyRecap', fn (User $user): bool => $user->isAdmin());
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
     * Tab mana yang boleh dilihat pengguna ini, ditentukan di sini — bukan di
     * Blade — supaya setiap task berikutnya cukup menambah satu entri.
     *
     * Wali kelas ditentukan relasi `homeroomClasses()`, bukan kolom
     * `role`; guru biasa yang kebetulan mengampu kelas tidak mendapat tab ini.
     *
     * Setiap entri adalah `['label' => ..., 'component' => ...]`; view
     * `recap.index` merender nav dan komponennya persis dari daftar ini.
     */
    protected function registerRecapTabs(): void
    {
        View::composer('recap.index', function ($view): void {
            $user = auth()->user();

            /** @var array<int,array{label: string, component: string}> $tabs */
            $tabs = [];

            if ($user instanceof User && $user->homeroomClasses()->exists()) {
                $tabs[] = ['label' => 'Kelas Saya', 'component' => 'homeroom-class-recap'];
            }

            if ($user instanceof User && ! $user->isAdmin()) {
                $tabs[] = ['label' => 'Mapel Saya', 'component' => 'teacher-subject-recap'];
            }

            if ($user instanceof User && $user->isAdmin()) {
                $tabs[] = ['label' => 'Semua Absensi', 'component' => 'admin-attendance-report'];
            }

            $view->with('tabRekap', $tabs);
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
