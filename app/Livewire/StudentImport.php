<?php

namespace App\Livewire;

use App\Imports\StudentRowValidator;
use App\Imports\StudentSheetReader;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class StudentImport extends Component
{
    use WithFileUploads;

    public string $academic_year_id = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public ?string $namaFile = null;

    /** @var array<int, array<string, mixed>> */
    public array $pratinjau = [];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'file' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
                'max:5120',
            ],
        ];
    }

    public function mount(): void
    {
        Gate::authorize('create', Student::class);

        if ($this->academic_year_id === '') {
            $this->academic_year_id = (string) (AcademicYear::query()
                ->where('is_active', true)
                ->value('id') ?? AcademicYear::query()->orderByDesc('start_date')->value('id'));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function barisGagal(): array
    {
        return array_values(array_filter(
            $this->pratinjau,
            fn (array $baris) => $baris['valid'] === false
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function barisValid(): array
    {
        return array_values(array_filter(
            $this->pratinjau,
            fn (array $baris) => $baris['valid'] === true
        ));
    }

    public function preview(): void
    {
        $this->validate();

        $kelas = SchoolClass::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->orderBy('name')
            ->get();

        $berkas = $this->file;

        if (! $berkas instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak terbaca. Unggah ulang.',
            ]);
        }

        $jalur = $berkas->getRealPath();

        $pembaca = new StudentSheetReader;

        Excel::import($pembaca, $jalur, null, ExcelType::XLSX);

        $this->pratinjau = app(StudentRowValidator::class)->validate($pembaca->rows(), $kelas);
        $this->namaFile = $berkas->getClientOriginalName();

        $this->file = null;

        $this->resetValidation();
    }

    public function confirm(): void
    {
        $gagal = $this->barisGagal();

        if ($gagal !== []) {
            throw ValidationException::withMessages([
                'file' => count($gagal).' baris ditolak. Perbaiki file-nya lalu unggah ulang. Tidak ada siswa yang disimpan sebagian.',
            ]);
        }

        Gate::authorize('create', Student::class);

        $jumlah = app(StudentImporter::class)->import($this->barisValid());

        session()->flash('sukses', $jumlah.' siswa berhasil diimpor.');

        $this->pratinjau = [];
        $this->namaFile = null;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->pratinjau = [];
        $this->namaFile = null;
        $this->file = null;
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('create', Student::class);

        return view('livewire.student-import', [
            'tahunAjaran' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'jumlahValid' => count($this->barisValid()),
            'jumlahGagal' => count($this->barisGagal()),
        ])->title('Impor Siswa');
    }
}
