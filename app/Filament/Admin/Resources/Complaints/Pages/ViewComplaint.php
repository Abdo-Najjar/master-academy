<?php

namespace App\Filament\Admin\Resources\Complaints\Pages;

use App\Filament\Admin\Resources\Complaints\ComplaintResource;
use App\Models\Complaint;
use App\Notifications\ComplaintReplied;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('respond')
                ->label(__('Respond & Update Status'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->schema([
                    Select::make('status')
                        ->label(__('Status'))
                        ->options(Complaint::statuses())
                        ->required()
                        ->default(fn (Complaint $record) => $record->status)
                        ->native(false),
                    Textarea::make('admin_reply')
                        ->label(__('Admin Reply'))
                        ->rows(5)
                        ->default(fn (Complaint $record) => $record->admin_reply),
                ])
                ->action(function (Complaint $record, array $data): void {
                    $record->update([
                        'status' => $data['status'],
                        'admin_reply' => $data['admin_reply'] ?? $record->admin_reply,
                        'handled_by' => Auth::id(),
                        'resolved_at' => $data['status'] === Complaint::STATUS_RESOLVED ? now() : null,
                    ]);

                    // Notify the student/trainer who filed it, when there is a reply.
                    if (filled($data['admin_reply'] ?? null)) {
                        $record->loadMissing('complainable');
                        $record->complainable?->notify(new ComplaintReplied($record));
                    }

                    Notification::make()
                        ->success()
                        ->title(__('Complaint updated'))
                        ->send();
                }),
        ];
    }
}
