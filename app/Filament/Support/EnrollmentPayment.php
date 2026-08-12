<?php

namespace App\Filament\Support;

use App\Models\PaymentType;
use App\Models\Student;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * "Take the money now" block, shared by Quick Enroll and the registration form.
 *
 * Registering a student withdraws the charge from their wallet whether or not
 * they have the balance, so without this the desk had to enroll first and
 * deposit second — leaving every fresh student sitting at a negative balance in
 * between. These fields are not columns on any model: they are lifted out of
 * the payload by `collect()` and turned into a wallet deposit.
 */
class EnrollmentPayment
{
    /** Form keys this block owns; stripped before the record is written. */
    public const KEYS = ['payment_amount', 'payment_type_id', 'payment_date', 'receipt', 'payment_note'];

    /**
     * @param  Closure(Get): float  $total  what the enrollment is going to charge
     * @return list<Component>
     */
    public static function schema(Closure $total): array
    {
        return [
            Placeholder::make('total_due_display')
                ->label(__('Total To Be Paid'))
                ->content(fn (Get $get): string => number_format($total($get), 2).' ₪'),

            TextInput::make('payment_amount')
                ->label(__('Amount Received'))
                ->numeric()
                ->prefix('₪')
                ->default(0)
                ->minValue(0)
                ->live(debounce: 500)
                ->helperText(fn (Get $get): string => self::hint($total($get), (float) ($get('payment_amount') ?? 0)))
                // One click to settle the whole bill, which is what happens
                // most of the time at the desk.
                ->hintAction(
                    Action::make('payFull')
                        ->label(__('Pay in full'))
                        ->icon('heroicon-m-check-circle')
                        ->action(fn (Get $get, Set $set) => $set('payment_amount', $total($get)))
                ),

            Select::make('payment_type_id')
                ->label(__('Payment Type'))
                ->options(fn (): array => PaymentType::all()->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->required(fn (Get $get): bool => (float) ($get('payment_amount') ?? 0) > 0),

            DateTimePicker::make('payment_date')
                ->label(__('Transaction Date'))
                ->seconds(false)
                ->default(now())
                ->maxDate(now())
                ->native(false),

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

            Textarea::make('payment_note')
                ->label(__('Note'))
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    /** Live "covers it / short by X / X left as credit" line under the amount. */
    public static function hint(float $total, float $paid): string
    {
        $difference = round($paid - $total, 2);

        if (abs($difference) < 0.01) {
            return __('Covers the full amount.');
        }

        return $difference < 0
            ? __('Short by :amount — the rest stays owed on the wallet.', ['amount' => number_format(abs($difference), 2).' ₪'])
            : __(':amount will be left as credit on the wallet.', ['amount' => number_format($difference, 2).' ₪']);
    }

    /**
     * Deposit whatever was collected, then strip the payment keys so the caller
     * can hand the rest straight to the model.
     *
     * MUST run before the registration is created: RegistrationObserver reads
     * the balance as it stands *before* each charge to work out how much of it
     * is really funded (and therefore how much of the trainer's share to
     * credit). Depositing afterwards leaves the registration looking unpaid.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the payload without the payment keys
     */
    public static function collect(array $data, ?int $studentId = null): array
    {
        $amount = (float) ($data['payment_amount'] ?? 0);
        $studentId ??= $data['student_id'] ?? null;
        $student = $studentId ? Student::find($studentId) : null;

        if ($amount > 0 && $student) {
            $transaction = $student->depositFloat($amount, [
                'description' => __('Payment received at enrollment'),
                'note' => $data['payment_note'] ?? null,
                'payment_type_id' => $data['payment_type_id'] ?? null,
                'receipt_path' => $data['receipt'] ?? null,
                'transaction_date' => $data['payment_date'] ?? null,
            ]);

            // Back-date the movement when an earlier date was entered, so
            // statements list it on the day the money changed hands.
            if ($transaction && ! empty($data['payment_date'])) {
                $transaction->forceFill(['created_at' => Carbon::parse($data['payment_date'])])->saveQuietly();
            }

            $student->refresh();
        }

        foreach (self::KEYS as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
