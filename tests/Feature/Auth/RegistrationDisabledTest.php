<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    public function test_route_registrasi_tidak_ada(): void
    {
        $this->assertFalse(
            Route::has('register'),
            'Route registrasi harus dihapus total (D7)'
        );
    }

    public function test_get_dan_post_registrasi_tidak_ada(): void
    {
        $this->assertFalse(
            Route::has('register.store'),
            'Route penyimpanan registrasi harus dihapus total (D7)'
        );
    }

    public function test_route_lupa_password_tidak_ada(): void
    {
        $names = [
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
        ];

        foreach ($names as $name) {
            $this->assertFalse(
                Route::has($name),
                "Route {$name} harus tidak ada karena reset password lewat admin (D8)"
            );
        }
    }

    public function test_route_settings_tidak_ada(): void
    {
        $this->assertFalse(
            Route::has('profile.edit'),
            'Route profile harus dihapus karena halamannya tidak dibangun (D17)'
        );
    }
}
