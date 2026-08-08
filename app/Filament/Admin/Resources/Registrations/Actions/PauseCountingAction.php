<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Services\SessionBillingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Suspend / resume a per-session registration. While paused, held lessons stop
 * advancing this student's counter, and when they come back they continue from
 * exactly the same point.
 */
class PauseCountingAction
{
    public static function make(): Action
    {
        return Action::make('togglePauseCounting')
            ->label(fn (?Registration $record): string => $record?->paused_at
                ? __('Resume session counting')
                : __('Pause session counting'))
            ->icon(fn (?Registration $record): string => $record?->paused_at
                ? 'heroicon-o-play'
                : 'heroicon-o-pause')
            ->color(fn (?Registration $record): string => $record?->paused_at ? 'success' : 'gray')
            ->requiresConfirmation()
            ->visible(fn (?Registration $record): bool => $record !== null
                && $record->isPerSessionBilled()
                && (auth()->user()?->can('registration.update') ?? false))
            ->action(function (Registration $record): void {
                if ($record->paused_at) {
                    SessionBillingService::resume($record);
                    Notification::make()->success()->title(__('Session counting resumed'))->send();

                    return;
                }

                SessionBillingService::pause($record);
                Notification::make()->success()->title(__('Session counting paused'))->send();
            });
    }
}
