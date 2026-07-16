<?php

namespace App\Filament\Admin\Resources\Trainers\Actions;

use App\Models\PaymentType;
use App\Models\Trainer;
use Bavix\Wallet\Models\Wallet;
use Filament\Actions\Action;
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
            ->modalHeading(__('Deposit to Trainer Wallet'))
            ->schema(self::amountSchema())
            ->action(function (Trainer $record, array $data): void {
                self::ensureWallet($record);

                $record->depositFloat(
                    (float) $data['amount'],
                    self::buildMeta($data, __('Deposit to trainer wallet'))
                );

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
            ->modalHeading(__('Withdraw from Trainer Wallet'))
            ->schema(self::amountSchema())
            ->action(function (Trainer $record, array $data): void {
                self::ensureWallet($record);

                $record->forceWithdrawFloat(
                    (float) $data['amount'],
                    self::buildMeta($data, __('Withdraw from trainer wallet'))
                );

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
        ];
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
