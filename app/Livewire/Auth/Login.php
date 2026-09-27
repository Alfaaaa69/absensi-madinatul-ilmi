<?php

namespace App\Livewire\Auth;

use App\Actions\AttemptAuthentication;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth.card')]
class Login extends Component
{
    #[Validate('required|string|digits:18|max:20')]
    public string $nuptk = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('nullable|boolean')]
    public bool $remember = false;

    public function authenticate(AttemptAuthentication $action): void
    {
        $this->validate();

        try {
            $action->attempt([
                'nuptk' => $this->nuptk,
                'password' => $this->password,
                'remember' => $this->remember,
            ]);
        } finally {
            $this->password = '';
        }

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
