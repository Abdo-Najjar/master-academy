<?php

namespace App\Filament\Admin\Resources\Trainers\Actions;

use App\Models\PaymentType;
use App\Models\Trainer;
use App\Notifications\WalletTransaction;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class WalletActions
{
    public static function deposit(): Action
    {
        return Action::make('deposit')
            ->label(__('Deposit'))
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('trainer.wallet') ?? false)
            ->modalHeading(__('Deposit to Trainer Wallet'))
            ->schema(self::amountSchema())
            ->action(function (Trainer $record, array $data): void {
                self::ensureWallet($record);

                $transaction = $record->depositFloat(
                    (float) $data['amount'],
                    self::buildMeta($data, __('Deposit to trainer wallet'))
                );

                self::applyTransactionDate($transaction, $data);

                $record->notify(new WalletTransaction('deposit', (float) $data['amount']));

                Notification::make()
                    ->success()
                    ->title(__('Deposit successful'))
                    ->send();
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label(__('Withdraw'))
            ->icon('heroicon-o-minus-circle')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('trainer.wallet') ?? false)
            ->modalHeading(__('Withdraw from Trainer Wallet'))
            ->schema(self::amountSchema())
            ->action(function (Trainer $record, array $data): void {
                self::ensureWallet($record);

                $transaction = $record->forceWithdrawFloat(
                    (float) $data['amount'],
                    self::buildMeta($data, __('Withdraw from trainer wallet'))
                );

                self::applyTransactionDate($transaction, $data);

                $record->notify(new WalletTransaction('withdraw', (float) $data['amount']));

                Notification::make()
                    ->success()
                    ->title(__('Withdrawal successful'))
                    ->send();
            });
    }

    protected static function amountSchema(): array
    {
        return [
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->prefix('₪')
                ->required()
                ->minValue(0.01)
                ->step(0.01),
            DateTimePicker::make('transaction_date')
                ->label(__('Transaction Date'))
                ->seconds(false)
                ->default(now())
                ->maxDate(now())
                ->native(false),
            Select::make('payment_type_id')
                ->label(__('Payment Type'))
                ->options(PaymentType::all()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->native(false),
            Textarea::make('note')
                ->label(__('Note'))
                ->rows(3)
                ->maxLength(500)
                ->columnSpanFull(),
            FileUpload::make('receipt')
                ->label(__('Payment Receipt'))
                ->helperText(__('Attach the transfer/notification receipt (optional).'))
                ->disk('public')
                ->directory('payment-receipts')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
        ];
    }

    protected static function buildMeta(array $data, string $description): array
    {
        return [
            'description' => $description,
            'note' => $data['note'] ?? null,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'receipt_path' => $data['receipt'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? null,
        ];
    }

    /**
     * Back-date the wallet transaction when the operator entered a date other
     * than "now", so statements list the payment on the day it actually happened.
     */
    protected static function applyTransactionDate(?Transaction $transaction, array $data): void
    {
        $date = $data['transaction_date'] ?? null;

        if (! $transaction || ! $date) {
            return;
        }

        $transaction->forceFill(['created_at' => Carbon::parse($date)])->saveQuietly();
    }

    protected static function ensureWallet(Trainer $trainer): void
    {
        if (! $trainer->wallet instanceof Wallet) {
            $trainer->createWallet([
                'name' => 'Default',
                'slug' => 'default',
            ]);
            $trainer->refresh();
        }
    }
}
