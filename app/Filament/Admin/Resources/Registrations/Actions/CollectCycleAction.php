<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Services\SessionBillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Collect the next payment cycle on a per-session registration: extends the
 * paid-through horizon by the section's sessions-per-cycle and raises the money
 * fields, which the registration observer turns into a wallet charge and the
 * trainer's share.
 */
class CollectCycleAction
{
    public static function make(): Action
    {
        return Action::make('collectCycle')
            ->label(__('Collect Session Cycle Payment'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (?Registration $record): bool => $record !== null
                && $record->isPerSessionBilled()
                && (auth()->user()?->can('registration.collect') ?? false))
            ->modalHeading(__('Collect Session Cycle Payment'))
            ->schema(fn (Registration $record): array => [
                Placeholder::make('state')
                    ->label(__('Sessions Counted'))
                    ->content(__(':counted of :paid paid sessions', [
                        'counted' => $record->sessions_counted,
                        'paid' => $record->paid_through_session,
                    ])),
                TextInput::make('cycles')
                    ->label(__('Number of cycles'))
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('₪')
                    ->default(fn (): float => (float) ($record->section?->cycle_fee ?? 0))
                    ->helperText(__('Defaults to the section cycle fee.')),
            ])
            ->action(function (Registration $record, array $data): void {
                $cycles = max(1, (int) ($data['cycles'] ?? 1));
                $amount = isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
                    ? (float) $data['amount'] * $cycles
                    : null;

                SessionBillingService::payCycle($record, $cycles, $amount);

                Notification::make()
                    ->success()
                    ->title(__('Payment recorded'))
                    ->body(__(':counted of :paid paid sessions', [
                        'counted' => $record->sessions_counted,
                        'paid' => $record->paid_through_session,
                    ]))
                    ->send();
            });
    }
}
