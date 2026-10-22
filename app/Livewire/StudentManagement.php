<?php

namespace App\Livewire;

use App\Enums\HasilHapus;
use App\Enums\StudentGender;
use App\Livewire\Concerns\ConfirmableDeletion;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class StudentManagement extends Component
{
    use ConfirmableDeletion;

    public const DELETE_MODAL = 'hapus-siswa';

    public ?string $editingId = null;

    public string $class_id = '';

    public string $nisn = '';

    public string $name = '';

    public string $gender = 'L';

    public string $search = '';

    public string $filterClassId = '';

    public string $filterGender = '';

    public bool $showArchived = false;

    public bool $showFormModal = false;

    public int $page = 1;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'class_id' => ['required', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'nisn' => [
                'required',
                'string',
                'max:20',
                Rule::unique('students', 'nisn')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'gender' => [
                'required',
                Rule::in(array_map(fn (StudentGender $item) => $item->value, StudentGender::cases())),
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedFilterClassId(): void
    {
        $this->page = 1;
    }

    public function updatedFilterGender(): void
    {
        $this->page = 1;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function newForm(): void
    {
        $this->editingId = null;
        $this->class_id = '';
        $this->nisn = '';
        $this->name = '';
        $this->gender = StudentGender::L->value;
        $this->resetValidation();
    }

    public function openCreateModal(): void
    {
        $this->newForm();
        $this->showFormModal = true;
    }

    public function closeModal(): void
    {
        $this->newForm();
        $this->showFormModal = false;
    }

    public function editForm(string $id): void
    {
        $siswa = Student::withTrashed()->findOrFail($id);

        Gate::authorize('update', $siswa);

        $this->editingId = $siswa->id;
        $this->class_id = $siswa->class_id;
        $this->nisn = $siswa->nisn;
        $this->name = $siswa->name;
        $this->gender = $siswa->gender->value;
        $this->showFormModal = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $siswa = $this->editingId === null ? null : Student::withTrashed()->findOrFail($this->editingId);

        Gate::authorize($siswa === null ? 'create' : 'update', $siswa ?? Student::class);

        $baru = $siswa === null;

        app(StudentManager::class)->save($siswa, [
            'class_id' => $this->class_id,
            'nisn' => $this->nisn,
            'name' => $this->name,
            'gender' => $this->gender,
        ]);

        $this->newForm();
        $this->showFormModal = false;

        session()->flash('sukses', $baru ? 'Siswa ditambahkan.' : 'Siswa diperbarui.');
    }

    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $siswa = Student::findOrFail($this->pendingDeleteId);

        Gate::authorize('delete', $siswa);

        $hasil = app(StudentManager::class)->hapus($siswa);

        session()->flash('sukses', $hasil === HasilHapus::Permanen
            ? "{$siswa->name} dihapus permanen."
            : "{$siswa->name} diarsipkan, bukan dihapus permanen. Siswa ini punya riwayat absensi yang harus tetap bisa dibaca.");

        $this->cancelDelete();
    }

    protected function deletableRecord(string $id): Student
    {
        return Student::findOrFail($id);
    }

    /**
     * @param  Student  $record
     */
    protected function deletableDescription(Model $record): string
    {
        return $record->name;
    }

    /**
     * @param  Student  $record
     */
    protected function permanentDeleteMessage(Model $record): string
    {
        return "{$record->name} dihapus permanen.";
    }

    /**
     * @param  Student  $record
     */
    protected function archiveDeleteMessage(Model $record): string
    {
        return "{$record->name} diarsipkan, bukan dihapus permanen. Siswa ini punya riwayat absensi yang harus tetap bisa dibaca.";
    }

    public function pulihkan(string $id): void
    {
        $siswa = Student::withTrashed()->findOrFail($id);

        Gate::authorize('update', $siswa);

        app(StudentManager::class)->pulihkan($siswa);

        session()->flash('sukses', "{$siswa->name} dipulihkan.");
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;
        $this->page = 1;
    }

    public function render(): View
    {
        Gate::authorize('create', Student::class);

        return view('livewire.student-management', [
            'daftarKelas' => SchoolClass::query()
                ->with('academicYear')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(),
            'jenisKelamin' => StudentGender::cases(),
            'siswa' => $this->daftarSiswa()
                ->with('schoolClass.academicYear')
                ->orderBy('nisn')
                ->forPage($this->page, 15)
                ->get(),
            'totalSiswa' => $this->daftarSiswa()->count(),
            'totalAktif' => Student::count(),
            'totalLaki' => Student::where('gender', StudentGender::L)->count(),
            'totalPerempuan' => Student::where('gender', StudentGender::P)->count(),
        ])->title('Siswa');
    }

    /**
     * Daftar dan total harus memakai filter yang sama, jadi rantainya
     * dibangun di satu tempat. Kalau dirantai dua kali, filter yang
     * berubah di salah satunya membuat total tidak cocok dengan isi tabel.
     *
     * @return Builder<Student>
     */
    private function daftarSiswa(): Builder
    {
        return Student::query()
            // Label tombol hapus per baris: "Arsipkan" kalau siswa masih
            // meninggalkan jejak absensi, "Hapus" kalau bersih. Satu subquery
            // untuk seluruh daftar, bukan query per baris.
            ->addSelect(['memiliki_riwayat' => Attendance::query()
                ->selectRaw('1')
                ->whereColumn('attendances.student_id', 'students.id')
                ->limit(1)])
            ->when($this->showArchived, fn (Builder $query) => $query->onlyTrashed())
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('nisn', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%');
            }))
            ->when($this->filterClassId !== '', fn (Builder $query) => $query->where('class_id', $this->filterClassId))
            ->when($this->filterGender !== '', fn (Builder $query) => $query->where('gender', $this->filterGender));
    }
}
