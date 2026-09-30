<?php

namespace App\Livewire;

use App\Attendance\Queries\TodaySchedulesQuery;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Jadwal Mengajar Hari Ini')]
class TodaySchedule extends Component
{
    public function render(): View
    {
        $tanggal = now()->startOfDay();
        $jadwal = app(TodaySchedulesQuery::class)->for(auth()->user(), $tanggal);

        $jumlahSiswa = SchoolClass::query()
            ->withCount('students')
            ->whereIn('id', $jadwal->pluck('class_id')->unique())
            ->pluck('students_count', 'id');

        $tercatat = Attendance::query()
            ->whereIn('schedule_id', $jadwal->pluck('id'))
            ->whereDate('date', $tanggal->toDateString())
            ->selectRaw('schedule_id, count(*) as total')
            ->groupBy('schedule_id')
            ->pluck('total', 'schedule_id');

        $baris = $jadwal->map(fn (Schedule $item) => [
            'jadwal' => $item,
            'jumlahSiswa' => (int) ($jumlahSiswa[$item->class_id] ?? 0),
            'sudahAbsen' => (int) ($tercatat[$item->id] ?? 0) > 0,
        ]);

        return view('livewire.today-schedule', [
            'tanggal' => $tanggal,
            'baris' => $baris,
        ]);
    }
}
