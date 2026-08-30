<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\PaymentType;
use App\Models\Registration;
use App\Notifications\WalletTransaction;
use App\Services\FinancialDueService;
use App\Services\TrainerPayoutService;
use Bavix\Wallet\Models\Wallet;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

/**
 * Take money for *one* registration, in whatever amount was handed over.
 *
 * A student sitting in three courses has three separate bills, but the wallet
 * has a single balance: a plain deposit settles their oldest under-funded
 * registration first (TrainerPayoutService::settleForStudent), so the desk had
 * no way to say "this 40 is for the maths course". This action does — it banks
 * the money and credits it to the registration it was collected against, and
 * only that one.
 *
 * Anything paid beyond what this registration still owes stays on the wallet as
 * ordinary credit rather than silently leaking into another course's bill.
 */
class CollectPaymentAction
{
    public static function make(): Action
    {
        return Action::make('collectPayment')
            ->label(__('Record Payment'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (?Registration $record): bool => $record !== null
                && (auth()->user()?->can('registration.collect') ?? false))
            ->modalHeading(__('Record Payment'))
            ->modalDescription(fn (Registration $record): string => $record->sectionLabel())
            ->modalSubmitActionLabel(__('Record Payment'))
            ->schema(fn (Registration $record): array => self::schema($record))
            ->action(function (Registration $record, array $data): void {
                self::collect($record, $data);
            });
    }

    /** @return list<mixed> */
    protected static function schema(Registration $record): array
    {
        $record->loadMissing(['student', 'section.subject']);

        $remaining = FinancialDueService::remainingBalance($record);
        $money = fn (float $amount): string => number_format($amount, 2).' ₪';

        return [
            // Which bill this is, spelled out: the same student's other courses
            // are separate rows with separate balances.
            Placeholder::make('bill')
                ->label(__('Course'))
                ->content($record->section?->subject?->getTranslation('name', app()->getLocale(), false)
                    ?? $record->sectionLabel()),

            Placeholder::make('balance')
                ->label(__('Financial Status'))
                ->content(__('Charged :charged · Paid :paid · Remaining :remaining', [
                    'charged' => $money((float) $record->amount_paid),
                    'paid' => $money((float) $record->funded_amount),
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

            DateTimePicker::make('transaction_date')
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

    /** Live "settles it / short by X / X left as credit" line under the amount. */
    public static function hint(float $remaining, float $amount): string
    {
        if ($amount <= 0) {
            return __('Any amount can be collected — the rest stays owed on this course.');
        }

        $difference = round($amount - $remaining, 2);

        if (abs($difference) < 0.01) {
            return __('Covers the full amount.');
        }

        return $difference < 0
            ? __('Short by :amount — the rest stays owed on the wallet.', ['amount' => number_format(abs($difference), 2).' ₪'])
            : __(':amount will be left as credit on the wallet.', ['amount' => number_format($difference, 2).' ₪']);
    }

    /**
     * Bank the money and credit it to this registration alone.
     *
     * @param  array<string, mixed>  $data
     */
    public static function collect(Registration $record, array $data): void
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            return;
        }

        $record->loadMissing(['student', 'section.trainer']);
        $student = $record->student;

        if (! $student) {
            Notification::make()
                ->danger()
                ->title(__('No records found'))
                ->send();

            return;
        }

        DB::transaction(function () use ($record, $student, $data, $amount): void {
            if (! $student->wallet instanceof Wallet) {
                $student->createWallet(['name' => 'Default', 'slug' => 'default']);
                $student->refresh();
            }

            $transaction = $student->depositFloat($amount, [
                'description' => __('Payment for :student — :name', [
                    'student' => $record->studentLabel(),
                    'name' => $record->sectionLabel(),
                ]),
                'note' => $data['note'] ?? $record->contextLabel(),
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'receipt_path' => $data['receipt'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? null,
                'registration_id' => $record->getKey(),
            ]);

            // Back-date the movement when an earlier date was entered, so
            // statements list it on the day the money changed hands.
            if ($transaction && ! empty($data['transaction_date'])) {
                $transaction->forceFill(['created_at' => Carbon::parse($data['transaction_date'])])->saveQuietly();
            }

            // Directed, not FIFO: only what this course still owes is taken off
            // the deposit; any surplus stays on the wallet as credit.
            $applied = min($amount, FinancialDueService::remainingBalance($record));

            if ($applied > 0) {
                TrainerPayoutService::applyFundedDelta($record, (float) $record->funded_amount + $applied);
            }
        });

        $record->refresh();
        $student->notify(new WalletTransaction('deposit', $amount));

        Notification::make()
            ->success()
            ->title(__('Payment recorded'))
            ->body(__('Charged :charged · Paid :paid · Remaining :remaining', [
                'charged' => number_format((float) $record->amount_paid, 2).' ₪',
                'paid' => number_format((float) $record->funded_amount, 2).' ₪',
                'remaining' => number_format(FinancialDueService::remainingBalance($record), 2).' ₪',
            ]))
            ->send();
    }
}
