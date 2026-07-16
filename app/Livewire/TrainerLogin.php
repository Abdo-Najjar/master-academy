<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrainerLogin extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('trainer')->check()) {
            $this->redirect(route('trainer.dashboard'), navigate: true);
        }
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'username' => __('Username'),
            'password' => __('Password'),
        ];
    }

    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $key = 'trainer-login|'.Str::lower($this->username).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::guard('trainer')->attempt([
            'username' => $this->username,
            'password' => $this->password,
        ], $this->remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => __('The provided credentials do not match our records.'),
            ]);
        }

        $trainer = Auth::guard('trainer')->user();
        if ($trainer && ! $trainer->is_active) {
            Auth::guard('trainer')->logout();

            throw ValidationException::withMessages([
                'username' => __('Your account has been disabled. Please contact administration.'),
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirect(route('trainer.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.trainer-login');
    }
}
