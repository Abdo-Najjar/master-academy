<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintReplied extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'subject' => $this->complaint->subject,
            'reply' => $this->complaint->admin_reply,
            'status' => $this->complaint->status,
            'title' => __('The administration replied to your complaint'),
        ];
    }
}
