<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ParentLogin extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('parent')->check()) {
            $this->redirect(route('parent.dashboard'), navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $key = 'parent-login|'.Str::lower($this->phone).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'phone' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::guard('parent')->attempt([
            'phone' => $this->phone,
            'password' => $this->password,
        ], $this->remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'phone' => __('The provided credentials do not match our records.'),
            ]);
        }

        $parent = Auth::guard('parent')->user();
        if ($parent && ! $parent->is_active) {
            Auth::guard('parent')->logout();

            throw ValidationException::withMessages([
                'phone' => __('Your account has been disabled. Please contact administration.'),
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirect(route('parent.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.parent-login');
    }
}
