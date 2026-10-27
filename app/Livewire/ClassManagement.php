<?php

namespace App\Livewire;

use App\Enums\HasilHapus;
use App\Enums\UserRole;
use App\Livewire\Concerns\ConfirmableDeletion;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ClassManagement extends Component
{
    use ConfirmableDeletion;

    public const DELETE_MODAL = 'hapus-kelas';

    public ?string $editingId = null;

    public string $academic_year_id = '';

    public string $name = '';

    public string $grade_level = '7';

    public string $homeroom_teacher_id = '';

    public bool $showArchived = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:20',
                Rule::unique('classes', 'name')
                    ->where('academic_year_id', $this->academic_year_id)
                    ->ignore($this->editingId),
            ],
            'grade_level' => ['required', 'in:7,8,9'],
            'homeroom_teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where(fn (Builder $query) => $query
                        ->where('role', UserRole::Guru)
                        ->whereNull('deleted_at')),
            ],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', SchoolClass::class);

        if ($this->academic_year_id === '') {
            $aktif = AcademicYear::query()->where('is_active', true)->first();

            $this->academic_year_id = $aktif->id ?? (string) AcademicYear::query()->orderByDesc('start_date')->value('id');
        }
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->grade_level = '7';
        $this->homeroom_teacher_id = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $kelas = SchoolClass::withTrashed()->findOrFail($id);

        Gate::authorize('update', $kelas);

        $this->editingId = $kelas->id;
        $this->academic_year_id = $kelas->academic_year_id;
        $this->name = $kelas->name;
        $this->grade_level = (string) $kelas->grade_level;
        $this->homeroom_teacher_id = (string) $kelas->homeroom_teacher_id;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $kelas = $this->editingId === null ? null : SchoolClass::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($kelas === null ? 'create' : 'update', $kelas ?? SchoolClass::class);

        $baru = $kelas === null;

        app(ClassManager::class)->save($kelas, [
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'grade_level' => $this->grade_level,
            'homeroom_teacher_id' => $this->homeroom_teacher_id,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Kelas ditambahkan.' : 'Kelas diperbarui.');
    }

    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $kelas = SchoolClass::findOrFail($this->pendingDeleteId);

        Gate::authorize('delete', $kelas);

        $jumlahSiswa = $kelas->students()->count();
        $hasil = app(ClassManager::class)->hapus($kelas);

        session()->flash('sukses', match (true) {
            $hasil === HasilHapus::Permanen => "Kelas {$kelas->name} dihapus permanen.",
            $jumlahSiswa > 0 => "Kelas {$kelas->name} diarsipkan. {$jumlahSiswa} siswa dan riwayat absensinya tetap tersimpan.",
            default => "Kelas {$kelas->name} diarsipkan.",
        });

        $this->cancelDelete();
    }

    protected function deletableRecord(string $id): SchoolClass
    {
        return SchoolClass::findOrFail($id);
    }

    /**
     * @param  SchoolClass  $record
     */
    protected function deletableDescription(Model $record): string
    {
        return $record->name;
    }

    /**
     * @param  SchoolClass  $record
     */
    protected function permanentDeleteMessage(Model $record): string
    {
        return "Kelas {$record->name} dihapus permanen.";
    }

    /**
     * @param  SchoolClass  $record
     */
    protected function archiveDeleteMessage(Model $record): string
    {
        return "Kelas {$record->name} diarsipkan, bukan dihapus permanen. Siswa dan riwayat absensinya tetap tersimpan.";
    }

    public function pulihkan(string $id): void
    {
        $kelas = SchoolClass::withTrashed()->findOrFail($id);

        Gate::authorize('update', $kelas);

        app(ClassManager::class)->pulihkan($kelas);

        session()->flash('sukses', 'Kelas dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', SchoolClass::class);

        return view('livewire.class-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'waliTersedia' => User::query()
                ->where('role', UserRole::Guru)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'waliTerpakai' => SchoolClass::query()
                ->where('academic_year_id', $this->academic_year_id)
                ->whereNotNull('homeroom_teacher_id')
                ->pluck('name', 'homeroom_teacher_id')
                ->all(),
            'daftar' => SchoolClass::query()
                ->addSelect(['memiliki_riwayat' => Attendance::query()
                    ->selectRaw('1')
                    ->where(function ($query) {
                        $query->whereHas('schedule', fn ($q) => $q->withTrashed()->whereColumn('schedules.class_id', 'classes.id'))
                            ->orWhereHas('student', fn ($q) => $q->withTrashed()->whereColumn('students.class_id', 'classes.id'));
                    })
                    ->limit(1)])
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->with('academicYear')
                ->with('homeroomTeacher')
                ->withCount('students')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(),
        ])->title('Kelas');
    }
}
