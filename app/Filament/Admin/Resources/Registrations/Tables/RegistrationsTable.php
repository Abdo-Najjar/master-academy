<?php

namespace App\Filament\Admin\Resources\Registrations\Tables;

use App\Filament\Admin\Resources\Registrations\Actions\CollectCycleAction;
use App\Filament\Admin\Resources\Registrations\Actions\PauseCountingAction;
use App\Filament\Admin\Resources\Registrations\Actions\TransferSectionAction;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('student.name')->label(__('Student'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->sortable(),
                TextColumn::make('section.subject.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color(fn ($record) => $record->section?->subject?->color ? \Filament\Support\Colors\Color::hex($record->section->subject->color) : 'gray')
                    ->toggleable(),
                TextColumn::make('paymentType.name')->label(__('Payment'))->toggleable(),
                TextColumn::make('amount_due')->label(__('Due'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('exemptionType.name')->label(__('Exemption Type'))->placeholder('—')->toggleable(),
                TextColumn::make('exemption_amount')->label(__('Exemption'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('amount_paid')->label(__('Paid'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('trainer_amount')->label(__('Trainer Share'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('trainer_credited_amount')
                    ->label(__('Trainer Share Credited'))
                    ->money('ILS', decimalPlaces: 0)
                    ->badge()
                    ->color(fn (Registration $record): string => (float) $record->trainer_credited_amount >= (float) $record->trainer_amount ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('sessions_counted')
                    ->label(__('Sessions Counted'))
                    ->state(fn (Registration $record): string => $record->isPerSessionBilled()
                        ? $record->sessions_counted.' / '.$record->paid_through_session
                        : '—')
                    ->badge()
                    ->color(fn (Registration $record): string => match (true) {
                        ! $record->isPerSessionBilled() => 'gray',
                        $record->remainingSessions() <= 0 => 'danger',
                        $record->remainingSessions() <= 2 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),
                TextColumn::make('financial_status')
                    ->label(__('Financial Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => __('Paid'),
                        'warning' => __('Payment due soon'),
                        'due' => __('Payment Due'),
                        'overdue' => __('Overdue'),
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'warning' => 'warning',
                        'due' => 'warning',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_type_id')
                    ->label(__('Payment Type'))
                    ->relationship('paymentType', 'name')
                    ->preload(),
                SelectFilter::make('exemption_type_id')
                    ->label(__('Exemption Type'))
                    ->relationship('exemptionType', 'name')
                    ->preload(),
                SelectFilter::make('financial_status')
                    ->label(__('Financial Status'))
                    ->options([
                        'ok' => __('Paid'),
                        'warning' => __('Payment due soon'),
                        'due' => __('Payment Due'),
                        'overdue' => __('Overdue'),
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('receipt')
                        ->label(__('Print Receipt'))
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->url(fn (Registration $record): string => route('admin.pdf.receipt', $record), shouldOpenInNewTab: true),
                    CollectCycleAction::make(),
                    TransferSectionAction::make(),
                    PauseCountingAction::make(),
                    Action::make('cancel')
                        ->label(__('Cancel & Refund'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Cancel Registration'))
                        ->modalDescription(__('This will refund the student wallet and revert the trainer commission, then soft-delete the registration.'))
                        ->action(function (Registration $record): void {
                            $record->deleteWithWalletAdjustments();
                            Notification::make()->title(__('Registration cancelled'))->success()->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
