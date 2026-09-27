<?php

namespace App\Livewire;

use App\Enums\AcademicPeriod;
use App\Models\AcademicYear;
use App\Services\AcademicYearManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AcademicYearManagement extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public string $period = 'ganjil';

    public string $start_date = '';

    public string $end_date = '';

    public bool $is_active = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_years', 'name')->ignore($this->editingId),
            ],
            'period' => ['required', Rule::in(array_column(AcademicPeriod::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', AcademicYear::class);
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->period = AcademicPeriod::Ganjil->value;
        $this->start_date = '';
        $this->end_date = '';
        $this->is_active = false;
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $tahun = AcademicYear::findOrFail($id);

        Gate::authorize('update', $tahun);

        $this->editingId = $tahun->id;
        $this->name = $tahun->name;
        $this->period = $tahun->period->value;
        $this->start_date = $tahun->start_date->format('Y-m-d');
        $this->end_date = $tahun->end_date->format('Y-m-d');
        $this->is_active = $tahun->is_active;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $tahun = $this->editingId === null ? null : AcademicYear::findOrFail($this->editingId);

        Gate::authorize($tahun === null ? 'create' : 'update', $tahun ?? AcademicYear::class);

        $baru = $tahun === null;

        app(AcademicYearManager::class)->save($tahun, [
            'name' => $this->name,
            'period' => $this->period,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Tahun ajaran ditambahkan.' : 'Tahun ajaran diperbarui.');
    }

    public function setActive(string $id): void
    {
        $tahun = AcademicYear::findOrFail($id);

        Gate::authorize('update', $tahun);

        $aktif = ! $tahun->is_active;

        app(AcademicYearManager::class)->setActive($tahun, $aktif);

        session()->flash('sukses', $aktif ? 'Tahun ajaran diaktifkan.' : 'Tahun ajaran dinonaktifkan.');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        return view('livewire.academic-year-management', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'periode' => AcademicPeriod::cases(),
        ])->title('Tahun Ajaran');
    }
}
