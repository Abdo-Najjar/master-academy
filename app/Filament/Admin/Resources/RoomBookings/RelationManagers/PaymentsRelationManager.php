<?php

namespace App\Filament\Admin\Resources\RoomBookings\RelationManagers;

use App\Models\PaymentType;
use App\Models\RoomBookingPayment;
use App\Support\ReceiptAttachment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The instalments banked against one booking — the booking's statement.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->numeric()
                    ->prefix('₪')
                    ->required()
                    ->minValue(0.01)
                    ->step(0.01),
                Select::make('payment_type_id')
                    ->label(__('Payment Type'))
                    ->options(fn (): array => PaymentType::all()->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
                DateTimePicker::make('paid_at')
                    ->label(__('Transaction Date'))
                    ->seconds(false)
                    ->default(now())
                    ->required()
                    ->native(false),
                ReceiptAttachment::field(),
                Textarea::make('note')
                    ->label(__('Note'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->emptyStateHeading(__('No records found'))
            ->columns([
                TextColumn::make('paid_at')->label(__('Transaction Date'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->money('ILS')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Paid'))->money('ILS')),
                TextColumn::make('paymentType.name')->label(__('Payment Type'))->placeholder('—'),
                TextColumn::make('receipt')
                    ->label(__('Receipt'))
                    ->state(fn (RoomBookingPayment $record): ?string => $record->receiptUrl() ? __('View') : null)
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (RoomBookingPayment $record): ?string => $record->receiptUrl(), shouldOpenInNewTab: true)
                    ->placeholder(__('N/A')),
                TextColumn::make('note')->label(__('Note'))->limit(30)->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Record Payment'))
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (): bool => auth()->user()?->can('room_booking.collect') ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('room_booking.collect') ?? false),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('room_booking.collect') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('room_booking.collect') ?? false),
                ]),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}
