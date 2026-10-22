<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Blok @property di bawah bukan dokumentasi — larastan membacanya untuk memberi
 * tipe pada atribut Eloquent. Tanpa `role` di sini, casts() (`@return array<string,
 * string>`) menghapus informasi enum dan perbandingan `=== UserRole::Admin`
 * dianggap selalu false oleh phpstan.
 *
 * @property string $id
 * @property string $name
 * @property string $nuptk
 * @property string|null $email
 * @property string $password
 * @property bool $must_change_password
 * @property UserRole $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'nuptk', 'email', 'password', 'must_change_password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Dipakai layout starter kit: resources/views/components/desktop-user-menu.blade.php
     * memanggil auth()->user()->initials(). Tanpa method ini, setiap halaman yang
     * memakai sidebar akan 500.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = '';

        foreach ($parts as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));

            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials === '' ? '?' : $initials;
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function taughtSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }

    public function isHomeroomOf(SchoolClass $schoolClass): bool
    {
        return $this->id === $schoolClass->homeroom_teacher_id;
    }
}
