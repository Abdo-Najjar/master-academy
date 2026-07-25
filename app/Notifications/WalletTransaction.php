<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletTransaction extends Notification
{
    use Queueable;

    public function __construct(public string $type, public float $amount) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $routeName = $notifiable instanceof Student ? 'student.dashboard' : 'trainer.dashboard';
        $formattedAmount = number_format($this->amount, 2).' ₪';

        $title = $this->type === 'deposit'
            ? __('A deposit of :amount has been made to your wallet', ['amount' => $formattedAmount])
            : __('A withdrawal of :amount has been made from your wallet', ['amount' => $formattedAmount]);

        return [
            'type' => $this->type,
            'amount' => $this->amount,
            'title' => $title,
            'url' => route($routeName, ['tab' => 'transactions']),
        ];
    }
}
