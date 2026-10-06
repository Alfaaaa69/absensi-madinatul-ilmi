<?php

namespace App\Services;

use App\Enums\HasilHapus;
use App\Enums\UserRole;
use App\Exceptions\LastActiveAdminException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private readonly MasterDeletion $deletion) {}

    /**
     * @param  array{name: string, nuptk: string, email: ?string, password: string, role: string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $this->guardDuplicateNuptk($data['nuptk']);

            return User::query()->create([
                'name' => trim($data['name']),
                'nuptk' => $data['nuptk'],
                'email' => $this->normaliseEmail($data['email']),
                'password' => Hash::make($data['password']),
                'role' => UserRole::from($data['role']),
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{name: string, nuptk: string, email: ?string, role: string}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if ($data['nuptk'] !== $user->nuptk) {
                $this->guardDuplicateNuptk($data['nuptk']);
            }

            if ($this->wouldRemoveLastActiveAdmin($user, $data['role'])) {
                throw new LastActiveAdminException;
            }

            $user->name = trim($data['name']);
            $user->nuptk = $data['nuptk'];
            $user->email = $this->normaliseEmail($data['email']);
            $user->role = UserRole::from($data['role']);
            $user->save();

            return $user;
        });
    }

    public function resetPassword(User $user, string $plainPassword): void
    {
        $user->password = Hash::make($plainPassword);
        $user->save();
    }

    public function toggleActive(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new LastActiveAdminException;
        }

        if ($target->is_active && $this->isLastActiveAdmin($target)) {
            throw new LastActiveAdminException;
        }

        $target->is_active = ! $target->is_active;
        $target->save();
    }

    /**
     * Guru/admin tanpa riwayat absensi dihapus permanen beserta jadwal
     * mengajarnya. Kalau ada riwayat — sebagai pencatat maupun lewat jadwal
     * yang dia ajar — akunnya diarsipkan supaya absensinya tidak kehilangan
     * jejak orang yang mencatatnya.
     */
    public function delete(User $actor, User $target): HasilHapus
    {
        if ($actor->is($target)) {
            throw new LastActiveAdminException;
        }

        if ($this->isLastActiveAdmin($target)) {
            throw new LastActiveAdminException;
        }

        return $this->deletion->hapus($target);
    }

    /**
     * Admin aktif terakhir yang sedang diedit akan kehilangan aksesnya kalau
     * form mengubah role-nya jadi guru.
     */
    private function wouldRemoveLastActiveAdmin(User $user, string $newRole): bool
    {
        return $user->role === UserRole::Admin
            && $user->is_active
            && $newRole !== UserRole::Admin->value
            && $this->activeAdminCount() <= 1;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin
            && $user->is_active
            && $this->activeAdminCount() <= 1;
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }

    private function guardDuplicateNuptk(string $nuptk): void
    {
        if (User::withTrashed()->where('nuptk', $nuptk)->exists()) {
            throw ValidationException::withMessages([
                'form.nuptk' => ['NUPTK ini sudah dipakai user lain.'],
            ]);
        }
    }

    private function normaliseEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : $email;
    }
}
