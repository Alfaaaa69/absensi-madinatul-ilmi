<?php

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Absensi')]
class AttendanceEntry extends Component
{
    public Schedule $schedule;

    public Carbon $tanggal;

    public bool $sudahDisimpan = false;

    /**
     * @var array<string,array{nama:string,nisn:string,jk:string}>
     */
    public array $baris = [];

    /**
     * @var array<string,string>
     */
    public array $status = [];

    /**
     * @var array<string,string|null>
     */
    public array $note = [];

    /**
     * Nama parameter mount harus sama persis dengan parameter rute
     * (`tanggal_absensi`). Livewire mencocokkan berdasarkan nama: kalau
     * berubah jadi `tanggal`, rute berisi tanggal diam-diam datang sebagai
     * null dan halaman ini menampilkan hari ini — bug senyap tanpa error.
     */
    public function mount(Schedule $schedule, ?string $tanggal_absensi = null): void
    {
        $this->schedule = $schedule;
        $this->tanggal = $this->tanggalDari($tanggal_absensi);

        Gate::authorize('view', $this->schedule);
        Gate::authorize('recordAttendance', [$this->schedule, $this->tanggal]);

        $tersimpan = Attendance::query()
            ->where('schedule_id', $this->schedule->id)
            ->whereDate('date', $this->tanggal->toDateString())
            ->get(['student_id', 'status', 'note'])
            ->keyBy('student_id');

        $this->sudahDisimpan = $tersimpan->isNotEmpty();

        $siswa = $this->schedule->schoolClass->students()->orderBy('name')->get();

        foreach ($siswa as $orang) {
            $absensi = $tersimpan->get($orang->id);

            $this->baris[$orang->id] = [
                'nama' => $orang->name,
                'nisn' => $orang->nisn,
                'jk' => $orang->gender->value,
            ];

            $this->status[$orang->id] = $absensi?->status->value ?? AttendanceStatus::Hadir->value;
            $this->note[$orang->id] = $absensi?->note;
        }
    }

    /**
     * Tanggal terkunci. Nilai dari rute adalah satu-satunya sumber selain
     * hari ini, dan bentuknya harus persis Y-m-d supaya tidak ada tanggal
     * liar yang lolos ke query.
     */
    private function tanggalDari(?string $tanggal): Carbon
    {
        if ($tanggal === null) {
            return now()->startOfDay();
        }

        if (! Carbon::hasFormat($tanggal, 'Y-m-d')) {
            abort(404);
        }

        return Carbon::parse($tanggal, 'Asia/Jakarta')->startOfDay();
    }

    public function render(): View
    {
        return view('livewire.attendance-entry', [
            'tanggal' => $this->tanggal,
        ]);
    }
}
