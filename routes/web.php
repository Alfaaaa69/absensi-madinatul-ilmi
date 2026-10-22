<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\AdminAttendanceReport;
use App\Livewire\AttendanceEntry;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\TeacherSubjectRecap;
use App\Livewire\TodaySchedule;
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

Route::middleware(['auth', EnsureUserIsActive::class, EnsurePasswordChanged::class])->group(function () {
    Route::get('/ubah-password', ChangePassword::class)->name('password.change');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/user', UserManagement::class)->name('user.index');

    Route::get('/jadwal', TodaySchedule::class)->name('jadwal.index');

    Route::view('/rekap', 'recap.index')->name('rekap.index');

    Route::get('/rekap/mapel-saya', TeacherSubjectRecap::class)->name('rekap.mapel-saya');

    Route::get('/rekap/admin', AdminAttendanceReport::class)->name('rekap.admin');

    Route::get('/absensi/{schedule}', AttendanceEntry::class)
        ->middleware(EnsureUserIsActive::class)
        ->name('absensi.create');

    Route::get('/absensi/{schedule}/{tanggal_absensi}', AttendanceEntry::class)
        ->middleware(EnsureUserIsActive::class)
        ->name('absensi.show');

    require __DIR__.'/master.php';
});
