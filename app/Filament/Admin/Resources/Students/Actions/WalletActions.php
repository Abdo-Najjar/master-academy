<?php

namespace App\Filament\Admin\Resources\Students\Actions;

use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Student;
use App\Notifications\WalletTransaction;
use App\Services\PaymentAllocationService;
use App\Support\BranchContext;
use App\Support\ReceiptAttachment;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WalletActions
{
    public static function deposit(): Action
    {
        return Action::make('deposit')
            ->label(__('Deposit'))
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('student.wallet') ?? false)
            ->modalHeading(__('Deposit to Student Wallet'))
            ->modalWidth('2xl')
            ->schema(fn (Student $record): array => self::depositSchema($record))
            ->action(function (Student $record, array $data): void {
                self::handleDeposit($record, $data);
            });
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label(__('Withdraw'))
            ->icon('heroicon-o-minus-circle')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('student.wallet') ?? false)
            ->modalHeading(__('Withdraw from Student Wallet'))
            ->schema(self::amountSchema())
            ->action(function (Student $record, array $data): void {
                self::ensureWallet($record);

                $transaction = $record->forceWithdrawFloat(
                    (float) $data['amount'],
                    self::buildMeta($data, __('Withdraw from student wallet'), $record)
                );

                self::applyTransactionDate($transaction, $data);

                $record->notify(new WalletTransaction('withdraw', (float) $data['amount']));

                Notification::make()
                    ->success()
                    ->title(__('Withdrawal successful'))
                    ->body(__(':amount has been withdrawn', ['amount' => number_format((float) $data['amount'], 2).' ₪']))
                    ->send();
            });
    }

    /**
     * The deposit form, with a line per course the student owes on.
     *
     * The wallet is one balance but the bills are per course, so a plain deposit
     * could only guess which one the money was for (oldest first). Here the desk
     * says it outright — "the 50 he handed over is for maths" — and anything left
     * unassigned stays on the wallet as ordinary credit.
     *
     * @return list<mixed>
     */
    protected static function depositSchema(Student $student): array
    {
        $dues = PaymentAllocationService::outstandingFor($student);
        $total = self::totalDue($dues);

        return [
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->prefix('₪')
                ->required()
                ->minValue(0.01)
                ->step(0.01)
                ->default($total > 0 ? $total : null)
                ->live(debounce: 500),

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

            ...($dues->isEmpty() ? [] : [
                Section::make(__('Allocate to courses'))
                    ->description(__('Choose which courses this payment settles. Anything left over stays as wallet credit.'))
                    ->schema([
                        TextEntry::make('allocation_summary')
                            ->label(__('Total Outstanding'))
                            ->state(fn (Get $get): string => self::summary($total, $get))
                            ->columnSpanFull(),

                        ActionsComponent::make([
                            Action::make('autoAllocate')
                                ->label(__('Distribute automatically'))
                                ->icon('heroicon-m-sparkles')
                                ->link()
                                ->action(fn (Get $get, Set $set) => self::autoAllocate($dues, $get, $set)),
                            Action::make('clearAllocation')
                                ->label(__('Clear'))
                                ->icon('heroicon-m-x-mark')
                                ->color('gray')
                                ->link()
                                ->action(function (Set $set) use ($dues): void {
                                    foreach ($dues as $registration) {
                                        $set('allocations.'.$registration->getKey(), null);
                                    }
                                }),
                        ])->columnSpanFull(),

                        ...$dues->map(fn (Registration $registration) => self::allocationField($registration))->all(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]),

            Textarea::make('note')
                ->label(__('Note'))
                ->rows(3)
                ->maxLength(500)
                ->columnSpanFull(),

            ReceiptAttachment::pendingField(),
        ];
    }

    /** One course's line: what it owes, and how much of this deposit goes to it. */
    protected static function allocationField(Registration $registration): TextInput
    {
        $due = PaymentAllocationService::amountDueNow($registration);
        $cycles = PaymentAllocationService::outstandingCycles($registration);

        return TextInput::make('allocations.'.$registration->getKey())
            ->label(self::courseLabel($registration))
            ->helperText(self::dueHint($due, $cycles, $registration))
            ->numeric()
            ->prefix('₪')
            ->minValue(0)
            ->maxValue($due)
            ->step(0.01)
            ->live(debounce: 500)
            ->hintAction(
                Action::make('payCourseInFull'.$registration->getKey())
                    ->label(__('Pay in full'))
                    ->icon('heroicon-m-check-circle')
                    ->action(fn (Set $set) => $set('allocations.'.$registration->getKey(), $due))
            );
    }

    /** "الرياضيات — شعبة أ (الفرع)", falling back to the section when untitled. */
    protected static function courseLabel(Registration $registration): string
    {
        $course = $registration->section?->subject?->getTranslation('name', app()->getLocale(), false);

        return $course
            ? $course.' — '.$registration->sectionLabel()
            : $registration->sectionLabel();
    }

    /** What this course owes, and — on per-session courses — why. */
    protected static function dueHint(float $due, int $cycles, Registration $registration): string
    {
        $line = __('Outstanding: :amount', ['amount' => number_format($due, 2).' ₪']);

        if ($cycles > 0 && PaymentAllocationService::unbilledCycleValue($registration) > 0.009) {
            $line .= ' · '.__(':counted of :paid paid sessions', [
                'counted' => $registration->sessions_counted,
                'paid' => $registration->paid_through_session,
            ]);
        }

        return $line;
    }

    /** Live "assigned X of Y, Z left as credit" line above the course rows. */
    protected static function summary(float $total, Get $get): string
    {
        $amount = (float) ($get('amount') ?? 0);
        $assigned = self::assignedTotal($get('allocations'));
        $money = fn (float $value): string => number_format($value, 2).' ₪';

        $line = $money($total).' · '.__('Assigned').': '.$money($assigned);

        $left = round($amount - $assigned, 2);

        if ($left < -0.009) {
            return $line.' · '.__('Over the deposit by :amount', ['amount' => $money(abs($left))]);
        }

        return $line.' · '.__(':amount will be left as credit on the wallet.', ['amount' => $money($left)]);
    }

    /** Fill the course lines from the amount entered, oldest bill first. */
    protected static function autoAllocate(Collection $dues, Get $get, Set $set): void
    {
        $budget = round((float) ($get('amount') ?? 0), 2);

        foreach ($dues as $registration) {
            $due = PaymentAllocationService::amountDueNow($registration);
            $take = round(min($budget, $due), 2);

            $set('allocations.'.$registration->getKey(), $take > 0 ? $take : null);

            $budget = round($budget - $take, 2);
        }
    }

    /**
     * Bank the money, then hand each course the share the desk assigned to it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function handleDeposit(Student $student, array $data): void
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $allocations = self::normaliseAllocations($data['allocations'] ?? []);
        $assigned = round(array_sum($allocations), 2);

        // Assigning more than was handed over would fund bills with money that
        // never arrived, so stop before anything is written.
        if ($assigned - $amount > 0.009) {
            Notification::make()
                ->danger()
                ->title(__('Over the deposit by :amount', [
                    'amount' => number_format($assigned - $amount, 2).' ₪',
                ]))
                ->send();

            throw new Halt;
        }

        self::ensureWallet($student);

        $applied = 0.0;

        DB::transaction(function () use ($student, $data, $amount, $allocations, &$applied): void {
            $transaction = $student->depositFloat($amount, self::buildMeta($data, __('Deposit to student wallet'), $student));

            self::applyTransactionDate($transaction, $data);

            $student->refresh();

            // Directed first: each course takes only what it was assigned. The
            // deposit is already on the wallet, which is what lets a per-session
            // course close its used-up cycles out of it.
            foreach ($allocations as $registrationId => $share) {
                $registration = Registration::query()
                    ->where('student_id', $student->getKey())
                    ->with('section')
                    ->find($registrationId);

                if ($registration) {
                    $applied += PaymentAllocationService::apply($registration, $share);
                }
            }

            // Nothing was named, so spread it the way the desk would have: oldest
            // bill first, by the same rules — leaving the lines blank must settle
            // as much as filling them in would.
            if ($allocations === []) {
                $applied = PaymentAllocationService::autoApply($student, $amount);
            }
        });

        $student->notify(new WalletTransaction('deposit', $amount));

        Notification::make()
            ->success()
            ->title(__('Deposit successful'))
            ->body(__(':amount has been deposited', ['amount' => number_format($amount, 2).' ₪'])
                .($applied > 0.009
                    ? ' · '.__('Assigned').': '.number_format($applied, 2).' ₪'
                    : ''))
            ->send();
    }

    /**
     * Drop the blank and zero lines, and key what is left by registration id.
     *
     * @param  array<mixed, mixed>  $allocations
     * @return array<int, float>
     */
    protected static function normaliseAllocations(array $allocations): array
    {
        $clean = [];

        foreach ($allocations as $registrationId => $share) {
            $share = round((float) $share, 2);

            if ($share > 0.009) {
                $clean[(int) $registrationId] = $share;
            }
        }

        return $clean;
    }

    /** @param  array<mixed, mixed>|null  $allocations */
    protected static function assignedTotal(?array $allocations): float
    {
        return round(array_sum(array_map(
            fn ($share): float => (float) $share,
            $allocations ?? []
        )), 2);
    }

    protected static function totalDue(Collection $dues): float
    {
        return round($dues->sum(
            fn (Registration $registration): float => PaymentAllocationService::amountDueNow($registration)
        ), 2);
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
            ReceiptAttachment::pendingField(),
        ];
    }

    /**
     * The movement's metadata, with the receipt filed against the student.
     *
     * A wallet movement is a vendor model and cannot own media, so the voucher
     * hangs off the account whose balance moved and the movement remembers
     * which one. Receipts taken before this are still a plain path on older
     * rows, and are still read — see ReceiptAttachment::walletUrl().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function buildMeta(array $data, string $description, Student $payable): array
    {
        return [
            'description' => $description,
            'note' => $data['note'] ?? null,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'branch_id' => BranchContext::currentBranchId(),
            'receipt_media_id' => ReceiptAttachment::attachToWallet($payable, $data['receipt'] ?? null),
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

    protected static function ensureWallet(Student $student): void
    {
        if (! $student->wallet instanceof Wallet) {
            $student->createWallet([
                'name' => 'Default',
                'slug' => 'default',
            ]);
            $student->refresh();
        }
    }
}
