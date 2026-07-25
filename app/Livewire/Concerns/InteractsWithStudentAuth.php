<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait InteractsWithStudentAuth
{
    public function logout(): void
    {
        Auth::guard('student')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('student.login'), navigate: true);
    }

    public function markNotificationRead(string $notificationId): void
    {
        Auth::guard('student')->user()?->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllNotificationsRead(): void
    {
        Auth::guard('student')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }
}
