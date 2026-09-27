<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Services\SubjectManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SubjectManagement extends Component
{
    public ?string $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $showArchived = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('subjects', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->editingId),
            ],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', Subject::class);
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $mapel = Subject::withTrashed()->findOrFail($id);

        Gate::authorize('update', $mapel);

        $this->editingId = $mapel->id;
        $this->code = (string) $mapel->code;
        $this->name = $mapel->name;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $mapel = $this->editingId === null ? null : Subject::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($mapel === null ? 'create' : 'update', $mapel ?? Subject::class);

        $baru = $mapel === null;

        app(SubjectManager::class)->save($mapel, [
            'code' => $this->code,
            'name' => $this->name,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Mata pelajaran ditambahkan.' : 'Mata pelajaran diperbarui.');
    }

    public function arsipkan(string $id): void
    {
        $mapel = Subject::findOrFail($id);

        Gate::authorize('delete', $mapel);

        app(SubjectManager::class)->arsipkan($mapel);

        session()->flash('sukses', 'Mata pelajaran diarsipkan. Jadwal dan absensi lamanya tetap utuh.');
    }

    public function pulihkan(string $id): void
    {
        $mapel = Subject::withTrashed()->findOrFail($id);

        Gate::authorize('update', $mapel);

        app(SubjectManager::class)->pulihkan($mapel);

        session()->flash('sukses', 'Mata pelajaran dipulihkan.');
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Subject::class);

        return view('livewire.subject-management', [
            'daftar' => Subject::query()
                ->when($this->showArchived, fn ($query) => $query->onlyTrashed())
                ->orderBy('name')
                ->get(),
        ])->title('Mata Pelajaran');
    }
}
