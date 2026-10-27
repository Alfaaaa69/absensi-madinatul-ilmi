<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth.card')]
#[Title('Ganti Kata Sandi')]
class ChangePassword extends Component
{
    #[Validate('required|string')]
    public string $current_password = '';

    #[Validate('required|string|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::default()],
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ])->save();

        session()->flash('status', 'Kata sandi berhasil diubah.');

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
