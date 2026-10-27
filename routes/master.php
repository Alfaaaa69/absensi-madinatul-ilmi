<?php

use App\Livewire\AcademicYearManagement;
use App\Livewire\ClassManagement;
use App\Livewire\ScheduleManagement;
use App\Livewire\SchoolBreakManagement;
use App\Livewire\StudentImport;
use App\Livewire\StudentManagement;
use App\Livewire\SubjectManagement;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('master')->name('master.')->group(function (): void {
    Route::get('tahun-ajaran', AcademicYearManagement::class)->name('tahun-ajaran');
    Route::get('mapel', SubjectManagement::class)->name('mapel');
    Route::get('kelas', ClassManagement::class)->name('kelas');
    Route::get('jadwal', ScheduleManagement::class)->name('jadwal');
    Route::get('jeda', SchoolBreakManagement::class)->name('jeda');
    Route::get('siswa', StudentManagement::class)->name('siswa');
    Route::get('siswa/impor', StudentImport::class)->name('siswa.impor');
});
