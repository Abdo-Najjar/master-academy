<?php

namespace App\Filament\Admin\Resources\Registrations\Tables;

use App\Filament\Admin\Resources\Registrations\Actions\CollectCycleAction;
use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Registrations\Actions\PauseCountingAction;
use App\Filament\Admin\Resources\Registrations\Actions\TransferSectionAction;
use App\Filament\Admin\Resources\Registrations\Actions\WithdrawFromSectionAction;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsTable
{
    /**
     * @param  bool  $numbered  count the rows 1, 2, 3… instead of showing the
     *                          registration id. Inside one section that is what
     *                          "#" is read as — a roster position, not a
     *                          database key that starts at 40 because forty
     *                          students were enrolled elsewhere first.
     */
    public static function configure(Table $table, bool $numbered = false): Table
    {
        return $table
            ->columns([
                $numbered
                    ? TextColumn::make('index')->label('#')->rowIndex()
                    : TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('student.name')->label(__('Student'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->sortable(),
                TextColumn::make('section.subject.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color(fn ($record) => $record->section?->subject?->color ? Color::hex($record->section->subject->color) : 'gray')
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
                        'ok' => __('Settled'),
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
                TextColumn::make('enrolled_at')
                    ->label(__('Section Enrollment Date'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('left_at')
                    ->label(__('Withdrawal Date'))
                    ->date()
                    ->placeholder('—')
                    ->badge()
                    ->color('danger')
                    ->tooltip(fn (Registration $record): ?string => $record->leave_reason)
                    ->sortable(),
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
                Filter::make('withdrawn')
                    ->label(__('Withdrawal'))
                    ->schema([
                        Select::make('state')
                            ->label(__('Withdrawal'))
                            ->placeholder(__('All'))
                            ->options([
                                'active' => __('Still Enrolled'),
                                'withdrawn' => __('Withdrawn'),
                            ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['state'] ?? null) {
                        'active' => $query->whereNull('left_at'),
                        'withdrawn' => $query->whereNotNull('left_at'),
                        default => $query,
                    })
                    ->indicateUsing(fn (array $data): ?string => match ($data['state'] ?? null) {
                        'active' => __('Still Enrolled'),
                        'withdrawn' => __('Withdrawn'),
                        default => null,
                    }),
                SelectFilter::make('financial_status')
                    ->label(__('Financial Status'))
                    ->options([
                        'ok' => __('Settled'),
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
                    // Per-course, so a student sitting in three subjects can be
                    // paid off one subject at a time.
                    CollectPaymentAction::make(),
                    CollectCycleAction::make(),
                    TransferSectionAction::make(),
                    PauseCountingAction::make(),
                    WithdrawFromSectionAction::make(),
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
