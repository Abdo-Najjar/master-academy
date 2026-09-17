<?php

namespace App\Filament\Admin\Resources\RoomBookings\Actions;

use App\Models\PaymentType;
use App\Models\RoomBooking;
use App\Support\ReceiptAttachment;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Take an instalment against a booking.
 *
 * A hall is rarely settled in one go — a deposit holds the date and the rest
 * arrives later — so this records what was handed over now rather than marking
 * the whole booking paid. It asks for exactly what the student-payment modal
 * asks for, so the desk fills in the same fields either way.
 */
class CollectBookingPaymentAction
{
    public static function make(string $name = 'collectBookingPayment'): Action
    {
        return Action::make($name)
            ->label(__('Record Payment'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (?RoomBooking $record): bool => $record !== null
                && ! $record->isCancelled()
                && (auth()->user()?->can('room_booking.collect') ?? false))
            ->modalHeading(__('Record Payment'))
            ->modalDescription(fn (RoomBooking $record): string => $record->contextLabel())
            ->modalSubmitActionLabel(__('Record Payment'))
            ->schema(fn (RoomBooking $record): array => self::schema($record))
            ->action(function (RoomBooking $record, array $data): void {
                self::collect($record, $data);
            });
    }

    /** @return list<mixed> */
    public static function schema(RoomBooking $record): array
    {
        $remaining = $record->remainingAmount();
        $money = fn (float $amount): string => number_format($amount, 2).' ₪';

        return [
            TextEntry::make('balance')
                ->label(__('Financial Status'))
                ->state(__('Price :price · Paid :paid · Remaining :remaining', [
                    'price' => $money((float) $record->price),
                    'paid' => $money($record->paidAmount()),
                    'remaining' => $money($remaining),
                ])),

            TextInput::make('amount')
                ->label(__('Amount Received'))
                ->numeric()
                ->prefix('₪')
                ->required()
                ->minValue(0.01)
                ->step(0.01)
                ->default($remaining > 0 ? $remaining : null)
                ->live(debounce: 500)
                ->helperText(fn (Get $get): string => self::hint($remaining, (float) ($get('amount') ?? 0)))
                ->hintAction(
                    Action::make('payRemaining')
                        ->label(__('Pay in full'))
                        ->icon('heroicon-m-check-circle')
                        ->visible($remaining > 0)
                        ->action(fn (Set $set) => $set('amount', $remaining))
                ),

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
                ->maxDate(now())
                ->native(false),

            Textarea::make('note')
                ->label(__('Note'))
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),

            // The instalment does not exist yet, so the upload is held in the
            // form and moved onto it by `collect()` once the row is written.
            ReceiptAttachment::pendingField(),
        ];
    }

    /** Live "settles it / short by X / X paid over the price" line. */
    public static function hint(float $remaining, float $amount): string
    {
        if ($amount <= 0) {
            return __('Any amount can be collected — the rest stays owed on this booking.');
        }

        $difference = round($amount - $remaining, 2);

        if (abs($difference) < 0.01) {
            return __('Covers the full amount.');
        }

        return $difference < 0
            ? __('Short by :amount — the rest stays owed on this booking.', ['amount' => number_format(abs($difference), 2).' ₪'])
            : __(':amount more than the booking price.', ['amount' => number_format($difference, 2).' ₪']);
    }

    /** @param array<string, mixed> $data */
    public static function collect(RoomBooking $record, array $data): void
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return;
        }

        $payment = $record->payments()->create([
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'amount' => $amount,
            'paid_at' => $data['paid_at'] ?? now(),
            'note' => $data['note'] ?? null,
        ]);

        ReceiptAttachment::attach($payment, $data[ReceiptAttachment::COLLECTION] ?? null);

        $record->refresh();

        Notification::make()
            ->success()
            ->title(__('Payment recorded'))
            ->body(__('Price :price · Paid :paid · Remaining :remaining', [
                'price' => number_format((float) $record->price, 2).' ₪',
                'paid' => number_format($record->paidAmount(), 2).' ₪',
                'remaining' => number_format($record->remainingAmount(), 2).' ₪',
            ]))
            ->send();
    }
}
