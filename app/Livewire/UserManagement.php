<?php

namespace App\Livewire;

use App\Enums\HasilHapus;
use App\Enums\UserRole;
use App\Exceptions\LastActiveAdminException;
use App\Livewire\Concerns\ConfirmableDeletion;
use App\Models\Attendance;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserManagement extends Component
{
    use ConfirmableDeletion, WithPagination;

    public const DELETE_MODAL = 'hapus-user';

    public ?string $editingId = null;

    /**
     * Flux mencocokkan nama modal saat membuka <dialog>. Nama yang sama dipakai
     * di view sebagai atribut name, jadi keduanya didefinisikan di sini supaya
     * tidak bisa berbeda tanpa sengaja.
     */
    public const FORM_MODAL = 'form-user';

    public const RESET_MODAL = 'reset-password';

    public string $search = '';

    /** @var array{id: ?string, name: string, nuptk: string, email: string, role: string} */
    public array $form = [
        'id' => null,
        'name' => '',
        'nuptk' => '',
        'email' => '',
        'role' => 'guru',
    ];

    public bool $showForm = false;

    public bool $showArchived = false;

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $resetTargetId = null;

    public string $resetTargetName = '';

    /**
     * Halaman ini hanya boleh dirender oleh admin. Tanpa authorize di sini,
     * guru yang berhasil menyamar dengan URL /user tetap bisa membaca seluruh
     * daftar akun sekolah.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->form = [
            'id' => null,
            'name' => '',
            'nuptk' => '',
            'email' => '',
            'role' => UserRole::Guru->value,
        ];

        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
        $this->clearValidation();

        $this->dispatch('modal-show', name: self::FORM_MODAL);
    }

    public function edit(string $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('update', $user);

        $this->form = [
            'id' => $user->id,
            'name' => $user->name,
            'nuptk' => $user->nuptk,
            'email' => $user->email ?? '',
            'role' => $user->role->value,
        ];

        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
        $this->clearValidation();

        $this->dispatch('modal-show', name: self::FORM_MODAL);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->clearValidation();
    }

    public function save(UserService $service): void
    {
        $isUpdate = $this->form['id'] !== null;

        $this->authorize($isUpdate ? 'update' : 'create', $isUpdate
            ? User::findOrFail($this->form['id'])
            : User::class);

        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.nuptk' => [
                'required',
                'string',
                'digits:18',
                'max:20',
                Rule::unique('users', 'nuptk')->ignore($this->form['id']),
            ],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.role' => ['required', Rule::in([
                UserRole::Admin->value,
                UserRole::Guru->value,
            ])],
        ];

        if (! $isUpdate) {
            $rules['password'] = ['required', 'string', 'confirmed', Password::default()];
        }

        $this->validate($rules);

        try {
            if ($isUpdate) {
                $service->update(
                    User::findOrFail($this->form['id']),
                    $this->form
                );

                $this->dispatch('user-updated');
            } else {
                $service->create([
                    'name' => $this->form['name'],
                    'nuptk' => $this->form['nuptk'],
                    'email' => $this->form['email'],
                    'role' => $this->form['role'],
                    'password' => $this->password,
                ]);

                $this->dispatch('user-created');
            }
        } catch (LastActiveAdminException $exception) {
            $this->addError('form.role', $exception->getMessage());

            return;
        }

        $this->showForm = false;
        $this->password = '';
        $this->password_confirmation = '';
        $this->clearValidation();
    }

    public function toggleActive(string $id, UserService $service): void
    {
        $target = User::findOrFail($id);

        $this->authorize('update', $target);

        try {
            $service->toggleActive($this->actor(), $target);
        } catch (LastActiveAdminException $exception) {
            $this->dispatch('toast', type: 'danger', message: $exception->getMessage());
        }
    }

    public function confirmDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $target = User::findOrFail($this->pendingDeleteId);

        $this->authorize('delete', $target);

        try {
            $hasil = app(UserService::class)->delete($this->actor(), $target);
        } catch (LastActiveAdminException $exception) {
            $this->dispatch('toast', type: 'danger', message: $exception->getMessage());
            $this->cancelDelete();

            return;
        }

        $this->dispatch('toast', type: 'success', message: $hasil === HasilHapus::Permanen
            ? 'Akun dihapus permanen beserta jadwal mengajarnya.'
            : 'Akun diarsipkan karena masih punya riwayat absensi.');

        $this->cancelDelete();
    }

    protected function deletableRecord(string $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * @param  User  $record
     */
    protected function deletableDescription(Model $record): string
    {
        return $record->name;
    }

    /**
     * @param  User  $record
     */
    protected function permanentDeleteMessage(Model $record): string
    {
        return "Akun {$record->name} dihapus permanen beserta jadwal mengajarnya.";
    }

    /**
     * @param  User  $record
     */
    protected function archiveDeleteMessage(Model $record): string
    {
        return "Akun {$record->name} diarsipkan, bukan dihapus permanen — masih dipakai riwayat absensi yang harus tetap terbaca.";
    }

    public function toggleArsip(): void
    {
        $this->showArchived = ! $this->showArchived;

        $this->resetPage();
    }

    public function pulihkan(string $id, UserService $service): void
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->authorize('update', $user);

        $service->pulihkan($user);

        $this->dispatch('toast', type: 'success', message: "{$user->name} dipulihkan.");
    }

    public function startReset(string $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('update', $user);

        $this->resetTargetId = $user->id;
        $this->resetTargetName = $user->name;
        $this->password = '';
        $this->password_confirmation = '';
        $this->clearValidation();

        $this->dispatch('modal-show', name: self::RESET_MODAL);
    }

    public function cancelReset(): void
    {
        $this->resetTargetId = null;
        $this->resetTargetName = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->clearValidation();
    }

    public function confirmReset(UserService $service): void
    {
        $target = $this->resolveResetTarget();

        $this->authorize('update', $target);

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ]);

        $service->resetPassword($target, $this->password);

        $this->dispatch('password-reset', name: $this->resetTargetName);

        $this->cancelReset();
    }

    public function render(): View
    {
        return view('livewire.user-management', [
            'users' => User::query()
                // Label tombol hapus per baris: "Arsipkan" kalau akun masih
                // dipakai riwayat absensi, "Hapus" kalau bersih.
                ->addSelect(['memiliki_riwayat' => Attendance::query()
                    ->selectRaw('1')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('attendances.recorded_by', 'users.id')
                            ->orWhereColumn('attendances.updated_by', 'users.id')
                            ->orWhereHas('schedule', fn ($q) => $q->withTrashed()->whereColumn('schedules.teacher_id', 'users.id'));
                    })
                    ->limit(1)])
                ->when($this->showArchived, fn (Builder $query) => $query->withTrashed())
                ->when($this->search !== '', function (Builder $query): void {
                    $query->where(function (Builder $inner): void {
                        $inner->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('nuptk', 'like', '%'.$this->search.'%');
                    });
                })
                ->orderBy('name')
                ->paginate(15),
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * confirmReset() dipanggil langsung oleh Livewire, jadi resetTargetId bisa
     * null. Tanpa pemeriksaan ini, findOrFail(null) akan menulis pesan 404 yang
     * tidak menjelaskan apa pun.
     */
    private function resolveResetTarget(): User
    {
        if ($this->resetTargetId === null) {
            throw new AuthorizationException('Tidak ada reset password yang sedang berjalan.');
        }

        return User::findOrFail($this->resetTargetId);
    }

    private function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
