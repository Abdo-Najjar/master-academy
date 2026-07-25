<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait InteractsWithTrainerAuth
{
    public function logout(): void
    {
        Auth::guard('trainer')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('trainer.login'), navigate: true);
    }

    public function markNotificationRead(string $notificationId): void
    {
        Auth::guard('trainer')->user()?->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllNotificationsRead(): void
    {
        Auth::guard('trainer')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }
}
