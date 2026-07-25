<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AssignmentCreated extends Notification
{
    use Queueable;

    public function __construct(public Assignment $assignment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'title' => __('New assignment: :title', ['title' => $this->assignment->title]),
            'body' => $this->assignment->section?->name,
            'url' => route('student.assignments.show', $this->assignment),
        ];
    }
}
