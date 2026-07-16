<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrainerLoginActivities extends Component
{
    public function render()
    {
        $trainer = Auth::guard('trainer')->user();

        $loginActivities = $trainer
            ? $trainer->loginActivities()->orderByDesc('logged_in_at')->limit(50)->get()
            : collect();

        return view('livewire.trainer-login-activities', [
            'loginActivities' => $loginActivities,
        ]);
    }
}
