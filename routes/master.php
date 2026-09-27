<?php

use App\Livewire\AcademicYearManagement;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('master')->name('master.')->group(function (): void {
    Route::get('tahun-ajaran', AcademicYearManagement::class)->name('tahun-ajaran');
});
