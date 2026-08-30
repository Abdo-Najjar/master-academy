<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Services\SessionBillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Suspend / resume a per-session registration. Lessons held during the break
 * are never charged to this student, and when they come back they continue from
 * exactly the same point.
 *
 * Both ends of the break carry a date rather than "now", so a student who
 * stopped two months ago can have it recorded correctly after the fact — which
 * is the normal case when a centre is entering its history.
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
            ->schema(fn (?Registration $record): array => $record?->paused_at
                ? [
                    DatePicker::make('resumed_at')
                        ->label(__('Resume From'))
                        ->native(false)
                        ->default(now())
                        ->required()
                        ->helperText(__('Lessons held from this day on are charged again.')),
                ]
                : [
                    DatePicker::make('paused_from')
                        ->label(__('Pause From'))
                        ->native(false)
                        ->default(now())
                        ->required()
                        ->helperText(__('Lessons held from this day on stop being charged. Set it back to record a break that already happened.')),
                    TextInput::make('reason')
                        ->label(__('Reason'))
                        ->maxLength(255),
                ])
            ->visible(fn (?Registration $record): bool => $record !== null
                && $record->isPerSessionBilled()
                && (auth()->user()?->can('registration.update') ?? false))
            ->action(function (Registration $record, array $data): void {
                if ($record->paused_at) {
                    SessionBillingService::resume($record, $data['resumed_at'] ?? null);
                    Notification::make()->success()->title(__('Session counting resumed'))->send();

                    return;
                }

                SessionBillingService::pause($record, $data['paused_from'] ?? null, $data['reason'] ?? null);
                Notification::make()->success()->title(__('Session counting paused'))->send();
            });
    }
}
