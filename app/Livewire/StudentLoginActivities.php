<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithStudentAuth;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class StudentLoginActivities extends Component
{
    use InteractsWithStudentAuth;

    public function render()
    {
        $student = Auth::guard('student')->user();

        $loginActivities = $student
            ? $student->loginActivities()->orderByDesc('logged_in_at')->limit(50)->get()
            : collect();

        return view('livewire.student-login-activities', [
            'student' => $student,
            'notifications' => $student->notifications()->limit(15)->get(),
            'unreadNotificationsCount' => $student->unreadNotifications()->count(),
            'loginActivities' => $loginActivities,
        ]);
    }
}
