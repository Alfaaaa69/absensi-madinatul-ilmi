<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Models\User;
use App\Recap\Queries\TeacherSubjectRecapQuery;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeacherSubjectRecap extends Component
{
    use InteractsWithRecapPeriod;

    /**
     * @return array<string,list<string>>
     */
    protected function rules(): array
    {
        return $this->recapRules();
    }

    public function mount(): void
    {
        $this->initializeRecap();
        $this->periodType = 'monthly';
    }

    /**
     * `periodType` dinaikkan ke locked, jadi nilainya selalu bisa diubah dari
     * request browser. Mengunci di `mount()` saja tidak cukup karena `mount()`
     * hanya jalan sekali saat komponen dibuat; hook ini yang menutup jalur itu.
     */
    public function updatedPeriodType(): void
    {
        $this->periodType = 'monthly';
    }

    public function render(): View
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403, 'Halaman rekap hanya untuk pengguna yang terautentikasi.');
        }

        $tahun = $this->year();
        $periode = $this->period($tahun);

        return view('livewire.teacher-subject-recap', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'result' => $this->rekap($user, $tahun, $periode),
        ]);
    }

    private function rekap(User $user, ?AcademicYear $tahun, ?RecapPeriod $periode): ?RecapResult
    {
        if ($tahun === null || $periode === null) {
            return null;
        }

        return app(TeacherSubjectRecapQuery::class)->for($user, $tahun)->run($periode);
    }
}
