<?php

use App\Livewire\Auth\Login;
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
