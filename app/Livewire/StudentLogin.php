<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class StudentLogin extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('student')->check()) {
            $this->redirect(route('student.dashboard'), navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $key = 'student-login|'.Str::lower($this->username).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::guard('student')->attempt([
            'username' => $this->username,
            'password' => $this->password,
        ], $this->remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => __('The provided credentials do not match our records.'),
            ]);
        }

        $student = Auth::guard('student')->user();
        if ($student && ! $student->is_active) {
            Auth::guard('student')->logout();

            throw ValidationException::withMessages([
                'username' => __('Your account has been disabled. Please contact administration.'),
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirect(route('student.dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.student-login');
    }
}
