<?php

namespace App\Livewire;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ScheduleManagement extends Component
{
    public ?string $editingId = null;

    public string $academic_year_id = '';

    public string $class_id = '';

    public string $subject_id = '';

    public string $teacher_id = '';

    public string $day_of_week = '1';

    public string $start_time = '';

    public string $end_time = '';

    public bool $showArchived = false;

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
    }

    public function newForm(): void
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

        $this->newForm();

        session()->flash('sukses', $baru ? 'Jadwal ditambahkan.' : 'Jadwal diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $jadwal = Schedule::findOrFail($id);

        Gate::authorize('delete', $jadwal);

        app(ScheduleManager::class)->arsipkan($jadwal);

        session()->flash('sukses', 'Jadwal diarsipkan. Absensi lama pada jadwal ini tetap utuh.');
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

        $kelasTerpilih = SchoolClass::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return view('livewire.schedule-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'daftarKelas' => $kelasTerpilih,
            'daftarMapel' => Subject::query()->orderBy('name')->get(),
            'daftarGuru' => User::query()
                ->where('role', UserRole::Guru)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'hari' => DayOfWeek::cases(),
            'daftar' => Schedule::query()
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->whereHas('schoolClass', fn ($query) => $query
                    ->where('academic_year_id', $this->academic_year_id))
                ->with('schoolClass')
                ->with('subject')
                ->with('teacher')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(),
        ])->title('Jadwal Pelajaran');
    }
}
