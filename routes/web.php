<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Auth\Login;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/user', UserManagement::class)->name('user.index');
});
