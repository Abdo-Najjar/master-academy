<?php

namespace App\Filament\Admin\Resources\RoomBookings\Tables;

use App\Filament\Admin\Resources\RoomBookings\Actions\CollectBookingPaymentAction;
use App\Filament\Support\BranchField;
use App\Models\Room;
use App\Models\RoomBooking;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['room', 'branch', 'times'])
                ->withSum('payments as paid_total', 'amount'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label(__('Booking Title'))->searchable()->sortable()->wrap(),
                TextColumn::make('room.number')
                    ->label(__('Room'))
                    ->formatStateUsing(fn (?string $state): string => $state ? __('Room').' '.$state : '—')
                    ->sortable(),
                TextColumn::make('branch.name')->label(__('Branch'))->placeholder('—')->toggleable(),
                TextColumn::make('start_date')
                    ->label(__('Dates'))
                    ->state(fn (RoomBooking $record): string => $record->start_date?->format('Y-m-d')
                        .($record->end_date && ! $record->end_date->isSameDay($record->start_date)
                            ? ' → '.$record->end_date->format('Y-m-d')
                            : ''))
                    ->sortable(),
                TextColumn::make('times')
                    ->label(__('Booking Times'))
                    ->state(fn (RoomBooking $record): string => $record->timesLabel() ?: '—')
                    ->wrap(),
                TextColumn::make('client_name')->label(__('Booked By'))->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('client_phone')->label(__('Phone Number'))->searchable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->label(__('Booking Price'))
                    ->money('ILS')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Booking Income'))->money('ILS')),
                TextColumn::make('paid_total')
                    ->label(__('Paid'))
                    ->state(fn (RoomBooking $record): float => $record->paidAmount())
                    ->money('ILS')
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label(__('Remaining'))
                    ->state(fn (RoomBooking $record): float => $record->remainingAmount())
                    ->money('ILS')
                    ->color(fn (RoomBooking $record): string => $record->remainingAmount() > 0.009 ? 'danger' : 'success'),
                TextColumn::make('payment_status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->state(fn (RoomBooking $record): string => RoomBooking::paymentStatusOptions()[$record->paymentStatus()])
                    ->color(fn (RoomBooking $record): string => match ($record->paymentStatus()) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('status')
                    ->label(__('Booking Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoomBooking::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        RoomBooking::STATUS_CONFIRMED => 'success',
                        RoomBooking::STATUS_TENTATIVE => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label(__('Room'))
                    ->options(fn (): array => Room::query()->get()
                        ->mapWithKeys(fn (Room $room): array => [$room->id => __('Room').' '.$room->number])
                        ->all())
                    ->searchable()
                    ->preload(),
                BranchField::filter(),
                SelectFilter::make('status')
                    ->label(__('Booking Status'))
                    ->options(fn (): array => RoomBooking::statusOptions()),
                SelectFilter::make('payment_status')
                    ->label(__('Payment Status'))
                    ->options(fn (): array => RoomBooking::paymentStatusOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->wherePaymentStatus($data['value'])
                        : $query),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    // A booking "falls in" a window when its run overlaps it,
                    // not when it starts in it: a hall booked for the whole term
                    // belongs to every month of that term.
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('end_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_date', '<=', $date))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                CollectBookingPaymentAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('No records found'))
            ->defaultSort('start_date', 'desc');
    }
}
