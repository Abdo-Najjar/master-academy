<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Services\SectionWithdrawalService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Withdraw a student from a section, or undo it.
 *
 * This is the permanent counterpart to pausing: from the chosen day the
 * student is off the attendance sheet, stops being charged, and their seat is
 * free. What they attended and paid before that day is left exactly as it is —
 * cancelling and refunding the whole registration is a different action.
 *
 * The date is free to set in the past, because the usual case is recording a
 * student who stopped coming weeks ago.
 */
class WithdrawFromSectionAction
{
    public static function make(): Action
    {
        return Action::make('withdrawFromSection')
            ->label(fn (?Registration $record): string => $record?->hasLeft()
                ? __('Undo Withdrawal')
                : __('Withdraw From Section'))
            ->icon(fn (?Registration $record): string => $record?->hasLeft()
                ? 'heroicon-o-arrow-uturn-left'
                : 'heroicon-o-arrow-right-start-on-rectangle')
            ->color(fn (?Registration $record): string => $record?->hasLeft() ? 'success' : 'danger')
            ->modalHeading(fn (?Registration $record): string => $record?->hasLeft()
                ? __('Undo Withdrawal')
                : __('Withdraw From Section'))
            ->modalDescription(fn (?Registration $record): ?string => $record?->hasLeft()
                ? __('The student goes back into the section from the day they left, and the lessons held since then are charged to them again.')
                : null)
            ->requiresConfirmation(fn (?Registration $record): bool => (bool) $record?->hasLeft())
            ->schema(fn (?Registration $record): array => $record?->hasLeft()
                ? []
                : [
                    DatePicker::make('left_at')
                        ->label(__('Withdrawal Date'))
                        ->native(false)
                        ->default(now())
                        ->required()
                        ->minDate(fn (): ?string => $record?->enrolled_at?->toDateString())
                        ->helperText(__('The first day the student is no longer in the section. Lessons held from this day on are not counted, not charged, and the student is off the attendance sheet. Set it back to record someone who stopped coming a while ago.')),
                    TextInput::make('leave_reason')
                        ->label(__('Withdrawal Reason'))
                        ->maxLength(255),
                    Placeholder::make('sessions_state')
                        ->label(__('Sessions Counted'))
                        ->visible(fn (): bool => (bool) $record?->isPerSessionBilled())
                        ->content(fn (): string => __(':counted of :paid paid sessions', [
                            'counted' => $record?->sessions_counted,
                            'paid' => $record?->paid_through_session,
                        ])),
                ])
            ->visible(fn (?Registration $record): bool => $record !== null
                && (auth()->user()?->can('registration.withdraw') ?? false))
            ->action(function (Registration $record, array $data): void {
                if ($record->hasLeft()) {
                    SectionWithdrawalService::rejoin($record);

                    Notification::make()
                        ->success()
                        ->title(__('Withdrawal undone'))
                        ->body(__(':counted of :paid paid sessions', [
                            'counted' => $record->fresh()->sessions_counted,
                            'paid' => $record->fresh()->paid_through_session,
                        ]))
                        ->send();

                    return;
                }

                SectionWithdrawalService::withdraw(
                    $record,
                    $data['left_at'] ?? null,
                    $data['leave_reason'] ?? null,
                );

                $record = $record->fresh();
                $unused = $record->unusedPaidSessions();

                // Say it out loud rather than moving money: the student may
                // have paid for lessons they will now never take.
                Notification::make()
                    ->success()
                    ->title(__('Student withdrawn from the section'))
                    ->body($unused > 0
                        ? __('The student has :count paid sessions they will not use. Settle them from the wallet if the centre refunds them.', ['count' => $unused])
                        : __('Counting stopped on :date.', ['date' => $record->left_at?->toDateString()]))
                    ->persistent($unused > 0)
                    ->send();
            });
    }
}
