<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithRecapPeriod;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use App\Recap\Queries\HomeroomClassRecapQuery;
use App\Recap\RecapPeriod;
use App\Recap\RecapResult;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class HomeroomClassRecap extends Component
{
    use InteractsWithRecapPeriod;

    public ?string $classId = null;

    public ?string $studentId = null;

    private HomeroomClassRecapQuery $query;

    /**
     * @return array<string,list<string>>
     */
    protected function rules(): array
    {
        return [...$this->recapRules(), 'classId' => ['nullable', 'uuid']];
    }

    public function mount(): void
    {
        $this->initializeRecap();
    }

    public function updatedAcademicYearId(): void
    {
        $this->classId = null;
        $this->studentId = null;
    }

    public function lihatSiswa(string $studentId): void
    {
        $this->studentId = $studentId;
    }

    public function tutupRiwayat(): void
    {
        $this->studentId = null;
    }

    public function render(): View
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403, 'Halaman rekap hanya untuk pengguna yang terautentikasi.');
        }

        $this->query = app(HomeroomClassRecapQuery::class);

        $tahun = $this->year();
        $periode = $this->period($tahun);

        return view('livewire.homeroom-class-recap', [
            'tahunAjaran' => $tahun,
            'daftarTahun' => $this->yearOptions(),
            'daftarKelas' => $tahun === null ? collect() : $this->query->ownedClasses($user, $tahun),
            'result' => $tahun === null || $periode === null ? null : $this->rekap($user, $tahun, $periode),
        ]);
    }

    private function rekap(User $user, AcademicYear $tahun, RecapPeriod $periode): RecapResult
    {
        $this->query->for($user, $tahun);

        if ($this->classId !== null) {
            $kelas = SchoolClass::find($this->classId) ?? abort(404, 'Kelas tidak ditemukan.');

            Gate::authorize('viewRecap', $kelas);

            $this->query->forClass($kelas);
        }

        return $this->query->run($periode);
    }
}
