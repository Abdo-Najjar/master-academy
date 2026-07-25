<?php

namespace App\Services;

use App\Filament\Admin\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ComplaintAlertService
{
    /**
     * Broadcast a database notification to every admin user allowed to
     * view complaints, with a "View" button linking straight to the record.
     */
    public function notifyNewComplaint(Complaint $complaint): void
    {
        $recipients = User::query()->permission('complaint.index')->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->title(__('New complaint received'))
            ->body($complaint->subject)
            ->actions([
                Action::make('view')
                    ->label(__('View Complaint'))
                    ->url(ComplaintResource::getUrl('view', ['record' => $complaint->id])),
            ])
            ->sendToDatabase($recipients);
    }
}
