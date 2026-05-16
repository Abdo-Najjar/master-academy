<?php

namespace App\Filament\Admin\Resources\Registrations\Tables;

use App\Models\Registration;
use Filament\Actions\Action;
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
                TextColumn::make('section.subject.name')->label(__('Subject'))->toggleable(),
                TextColumn::make('paymentType.name')->label(__('Payment'))->toggleable(),
                TextColumn::make('amount_due')->label(__('Due'))->money('USD')->sortable(),
                TextColumn::make('exemption_amount')->label(__('Exemption'))->money('USD')->sortable(),
                TextColumn::make('amount_paid')->label(__('Paid'))->money('USD')->sortable(),
                TextColumn::make('trainer_amount')->label(__('Trainer Share'))->money('USD')->sortable(),
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
