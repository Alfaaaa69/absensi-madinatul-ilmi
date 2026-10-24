<?php

namespace App\Livewire;

use App\Enums\DayOfWeek;
use App\Models\SchoolBreak;
use App\Services\SchoolBreakManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SchoolBreakManagement extends Component
{
    public ?string $editingId = null;

    public string $day_of_week = '1';

    public string $label = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $after = '0';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
            'label' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'after' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', SchoolBreak::class);
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->day_of_week = '1';
        $this->label = '';
        $this->start_time = '';
        $this->end_time = '';
        $this->after = '0';
        $this->resetValidation();
    }

    public function editForm(string $id): void
    {
        $jeda = SchoolBreak::findOrFail($id);

        Gate::authorize('update', $jeda);

        $this->editingId = $jeda->id;
        $this->day_of_week = (string) $jeda->day_of_week->value;
        $this->label = $jeda->label;
        $this->start_time = $jeda->start_time->format('H:i');
        $this->end_time = $jeda->end_time->format('H:i');
        $this->after = (string) $jeda->after;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $jeda = $this->editingId === null ? null : SchoolBreak::findOrFail($this->editingId);

        Gate::authorize($jeda === null ? 'create' : 'update', $jeda ?? SchoolBreak::class);

        $baru = $jeda === null;

        app(SchoolBreakManager::class)->save($jeda, [
            'day_of_week' => $this->day_of_week,
            'label' => $this->label,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'after' => $this->after,
        ]);

        $this->newForm();

        session()->flash('sukses', $baru ? 'Jeda ditambahkan.' : 'Jeda diperbarui.');
    }

    public function hapus(string $id): void
    {
        $jeda = SchoolBreak::findOrFail($id);

        Gate::authorize('delete', $jeda);

        app(SchoolBreakManager::class)->hapus($jeda);

        if ($this->editingId === $id) {
            $this->newForm();
        }

        session()->flash('sukses', 'Jeda dihapus.');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', SchoolBreak::class);

        return view('livewire.school-break-management', [
            'daftar' => SchoolBreak::query()
                ->orderBy('day_of_week')
                ->orderBy('after')
                ->orderBy('start_time')
                ->get(),
            'hari' => DayOfWeek::cases(),
        ])->title('Atur Jeda');
    }
}
