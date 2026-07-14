<?php

namespace App\Filament\Admin\Resources\Registrations\Tables;

use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
                    ->label(__('Subject'))
                    ->badge()
                    ->color(fn ($record) => $record->section?->subject?->color ? \Filament\Support\Colors\Color::hex($record->section->subject->color) : 'gray')
                    ->toggleable(),
                TextColumn::make('paymentType.name')->label(__('Payment'))->toggleable(),
                TextColumn::make('amount_due')->label(__('Due'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('exemptionType.name')->label(__('Exemption Type'))->placeholder('—')->toggleable(),
                TextColumn::make('exemption_amount')->label(__('Exemption'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('amount_paid')->label(__('Paid'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('trainer_amount')->label(__('Trainer Share'))->money('ILS', decimalPlaces: 0)->sortable(),
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
                    DeleteAction::make(),
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
