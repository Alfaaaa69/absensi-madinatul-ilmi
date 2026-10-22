<?php

namespace App\Livewire;

use App\Enums\DayOfWeek;
use App\Enums\HasilHapus;
use App\Enums\UserRole;
use App\Livewire\Concerns\ConfirmableDeletion;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use App\Support\SchoolBreaks;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ScheduleManagement extends Component
{
    use ConfirmableDeletion;

    public const DELETE_MODAL = 'hapus-jadwal';

    public ?string $editingId = null;

    public string $academic_year_id = '';

    public string $class_id = '';

    public string $subject_id = '';

    public string $teacher_id = '';

    public string $day_of_week = '1';

    public string $start_time = '';

    public string $end_time = '';

    public string $searchJadwal = '';

    public bool $showArchived = false;

    public string $selectedClassId = '';

    public bool $showForm = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'class_id' => ['required', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'teacher_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('role', UserRole::Guru)
                        ->whereNull('deleted_at')),
            ],
            'day_of_week' => [
                'required',
                Rule::in(array_map(fn (DayOfWeek $hari) => (string) $hari->value, DayOfWeek::cases())),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('create', Schedule::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::query()
                ->where('is_active', true)
                ->value('id') ?? AcademicYear::query()->orderByDesc('start_date')->value('id'));
        }

        $this->selectedClassId = $this->firstClassId();
    }

    public function updatedAcademicYearId(): void
    {
        $this->selectedClassId = $this->firstClassId();
        $this->class_id = '';
        $this->resetValidation();
    }

    private function firstClassId(): string
    {
        return (string) SchoolClass::withTrashed()
            ->where('academic_year_id', $this->academic_year_id)
            ->orderBy('deleted_at')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->value('id');
    }

    public function updatedStartTime(): void
    {
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->start_time)) {
            return;
        }

        [$jam, $menit] = array_map('intval', explode(':', $this->start_time));
        $total = $jam * 60 + $menit + 45;

        if ($total >= 24 * 60) {
            return;
        }

        $this->end_time = sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public function newForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->class_id = '';
        $this->subject_id = '';
        $this->teacher_id = '';
        $this->day_of_week = '1';
        $this->start_time = '';
        $this->end_time = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $jadwal = Schedule::withTrashed()->findOrFail($id);

        Gate::authorize('update', $jadwal);

        $this->showForm = true;
        $this->selectedClassId = $jadwal->class_id;
        $this->editingId = $jadwal->id;
        $this->academic_year_id = $jadwal->schoolClass->academic_year_id;
        $this->class_id = $jadwal->class_id;
        $this->subject_id = $jadwal->subject_id;
        $this->teacher_id = $jadwal->teacher_id;
        $this->day_of_week = (string) $jadwal->day_of_week->value;
        $this->start_time = $jadwal->start_time->format('H:i');
        $this->end_time = $jadwal->end_time->format('H:i');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->showForm = true;
        $this->validate();

        $jadwal = $this->editingId === null ? null : Schedule::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($jadwal === null ? 'create' : 'update', $jadwal ?? Schedule::class);

        $baru = $jadwal === null;

        app(ScheduleManager::class)->save($jadwal, [
            'class_id' => $this->class_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ]);

        $this->closeForm();

        session()->flash('sukses', $baru ? 'Jadwal ditambahkan.' : 'Jadwal diperbarui.');
    }

    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $jadwal = Schedule::findOrFail($this->pendingDeleteId);

        Gate::authorize('delete', $jadwal);

        $hasil = app(ScheduleManager::class)->hapus($jadwal);

        session()->flash('sukses', $hasil === HasilHapus::Permanen
            ? 'Jadwal dihapus permanen.'
            : 'Jadwal diarsipkan. Absensi lama pada jadwal ini tetap utuh.');

        $this->cancelDelete();
    }

    protected function deletableRecord(string $id): Schedule
    {
        return Schedule::findOrFail($id);
    }

    /**
     * @param  Schedule  $record
     */
    protected function deletableDescription(Model $record): string
    {
        return $record->schoolClass->name.' - '.$record->subject->name;
    }

    /**
     * @param  Schedule  $record
     */
    protected function permanentDeleteMessage(Model $record): string
    {
        return 'Jadwal dihapus permanen dan slotnya dibebaskan.';
    }

    /**
     * @param  Schedule  $record
     */
    protected function archiveDeleteMessage(Model $record): string
    {
        return 'Jadwal diarsipkan, bukan dihapus permanen. Absensi lama pada jadwal ini tetap utuh.';
    }

    public function pulihkan(string $id): void
    {
        $jadwal = Schedule::withTrashed()->findOrFail($id);

        Gate::authorize('update', $jadwal);

        app(ScheduleManager::class)->pulihkan($jadwal);

        session()->flash('sukses', 'Jadwal dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Schedule::class);

        // Kelas yang sudah diarsipkan tetap bisa dipilih supaya jadwal lamanya
        // tidak hilang dari halaman ini.
        $kelasTerpilih = SchoolClass::withTrashed()
            ->where('academic_year_id', $this->academic_year_id)
            ->with('homeroomTeacher')
            ->orderBy('deleted_at')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $daftarMapel = Subject::query()
            ->orderBy('name')
            ->get();

        $daftarGuru = User::query()
            ->where('role', UserRole::Guru)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $daftar = Schedule::query()
            ->addSelect(['memiliki_riwayat' => Attendance::query()
                ->selectRaw('1')
                ->whereColumn('attendances.schedule_id', 'schedules.id')
                ->limit(1)])
            ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
            // Relasi schedule sudah withTrashed(), tapi whereHas memakai
            // query sendiri jadi harus disebut eksplisit. Tanpa ini jadwal
            // dengan kelas ter-arsipkan hilang dari daftar.
            ->whereHas('schoolClass', fn ($query) => $query
                ->withTrashed()
                ->where('academic_year_id', $this->academic_year_id))
            ->when($this->searchJadwal !== '', function ($query) {
                $term = '%'.$this->searchJadwal.'%';
                $query->where(function ($query) use ($term) {
                    $query->whereHas('schoolClass', fn ($q) => $q->withTrashed()->where('name', 'like', $term))
                        ->orWhereHas('subject', fn ($q) => $q->withTrashed()->where('name', 'like', $term))
                        ->orWhereHas('teacher', fn ($q) => $q->withTrashed()->where('name', 'like', $term));
                });
            })
            ->with(['schoolClass', 'subject', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $kelasAktif = $kelasTerpilih->firstWhere('id', $this->selectedClassId)
            ?? $kelasTerpilih->first();

        // Jam pelajaran tiap hari bisa berbeda, jadi baris grid memakai urutan
        // jam ke-1, ke-2, dst. Jam tepatnya ditampilkan di dalam kartu.
        $perHari = $daftar
            ->where('class_id', $kelasAktif?->id)
            ->groupBy(fn (Schedule $jadwal) => $jadwal->day_of_week->value);

        $perHari = collect(DayOfWeek::cases())->mapWithKeys(function (DayOfWeek $hari) use ($perHari) {
            $baris = ($perHari->get($hari->value) ?? collect())
                ->sortBy('start_time')
                ->values();

            return [$hari->value => $baris->mapWithKeys(function (Schedule $jadwal, int $index) use ($hari) {
                $nomor = SchoolBreaks::periodFor($hari, $jadwal->start_time->format('H:i')) ?? $index + 1;

                return [$nomor => $jadwal];
            })];
        });

        $jumlahBaris = $perHari->max(fn ($baris) => $baris->keys()->max() ?? 0);
        $jedaSenin = SchoolBreaks::forDay(DayOfWeek::Senin);
        $adaSenin = $perHari->get(DayOfWeek::Senin->value)->isNotEmpty();

        $grid = $jumlahBaris > 0
            ? collect(range(1, $jumlahBaris))->flatMap(function (int $nomor) use ($perHari, $jedaSenin, $adaSenin) {
                $baris = [[
                    'type' => 'lesson',
                    'nomor' => $nomor,
                    'hari' => $perHari->map(fn ($baris) => $baris->get($nomor)),
                ]];

                if ($adaSenin) {
                    foreach ($jedaSenin as $jeda) {
                        if ($jeda['after'] === $nomor) {
                            $baris[] = ['type' => 'break', 'jeda' => $jeda];
                        }
                    }
                }

                return $baris;
            })
            : collect();

        return view('livewire.schedule-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'daftarKelas' => $kelasTerpilih,
            'daftarMapel' => $daftarMapel,
            'daftarGuru' => $daftarGuru,
            'hari' => DayOfWeek::cases(),
            'daftar' => $daftar,
            'kelasAktif' => $kelasAktif,
            'grid' => $grid,
        ])->title('Jadwal Pelajaran');
    }
}
