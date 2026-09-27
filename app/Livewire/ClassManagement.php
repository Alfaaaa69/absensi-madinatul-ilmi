<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ClassManagement extends Component
{
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

    public function arsipkan(string $id): void
    {
        $kelas = SchoolClass::findOrFail($id);

        Gate::authorize('delete', $kelas);

        $jumlahSiswa = app(ClassManager::class)->arsipkan($kelas);

        session()->flash(
            'sukses',
            $jumlahSiswa > 0
                ? "Kelas {$kelas->name} diarsipkan. {$jumlahSiswa} siswa dan riwayat absensinya tetap tersimpan."
                : "Kelas {$kelas->name} diarsipkan."
        );
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
            'daftar' => SchoolClass::query()
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
