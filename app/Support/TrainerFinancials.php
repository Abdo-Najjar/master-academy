<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\Trainer;
use Bavix\Wallet\Models\Transaction;

/**
 * What a trainer has earned, what they have been handed, and what the centre
 * still owes them.
 *
 * The trainer's wallet is the ledger of record here, not `registrations`. Two
 * kinds of money reach it that no registration knows about — a private lesson's
 * share, and a straight deposit made by hand at the desk — so summing
 * `trainer_credited_amount` would under-report what they have actually earned.
 * Reading the wallet instead keeps the panel arithmetic honest:
 * credited − paid out = the balance on their page.
 *
 * `pending` is the one figure that cannot come from the wallet, because it is
 * money that has not moved: the trainer's share of charges their students have
 * not settled yet. The centre credits a share only as far as the student has
 * actually paid (see TrainerPayoutService), so a trainer teaching a section full
 * of debtors has earnings waiting behind those debts rather than in their
 * wallet.
 */
final class TrainerFinancials
{
    private function __construct(
        /** Everything ever deposited: teaching shares plus desk deposits. */
        public readonly float $credited,
        /**
         * Everything ever taken back out. Payouts to the trainer make up
         * nearly all of it; a clawback from a corrected or cancelled
         * registration is also money leaving the wallet, and is counted here
         * so the three figures still add up.
         */
        public readonly float $paidOut,
        /** What the centre still owes: the wallet balance. */
        public readonly float $balance,
        /** Share earned but not yet credited, because students still owe it. */
        public readonly float $pending,
    ) {}

    public static function for(Trainer $trainer): self
    {
        $totals = self::walletTotals($trainer);

        $pending = Registration::query()
            ->reportable()
            ->whereHas('section', fn ($query) => $query->where('trainer_id', $trainer->getKey()))
            ->toBase()
            // Per registration, not as a difference of totals: a share credited
            // beyond its own charge must not cancel out another student's debt.
            ->selectRaw('COALESCE(SUM(CASE WHEN trainer_amount > trainer_credited_amount THEN trainer_amount - trainer_credited_amount ELSE 0 END), 0) as pending')
            ->value('pending');

        return new self(
            credited: $totals['deposit'],
            paidOut: $totals['withdraw'],
            balance: round((float) $trainer->balanceFloat, 2),
            pending: round((float) $pending, 2),
        );
    }

    /** Everything they will have earned once the debts behind them are paid. */
    public function expectedTotal(): float
    {
        return round($this->credited + $this->pending, 2);
    }

    /**
     * Deposits and withdrawals on the trainer's wallet, as money.
     *
     * Amounts are stored as integers in the wallet's smallest unit, so they are
     * summed in the database and scaled once — and only confirmed rows count,
     * which is the same set the balance is built from.
     *
     * @return array{deposit: float, withdraw: float}
     */
    private static function walletTotals(Trainer $trainer): array
    {
        $scale = 10 ** (int) ($trainer->wallet?->decimal_places ?? 2);

        $sums = $trainer->transactions()
            ->where('confirmed', true)
            ->toBase()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'deposit' => round((float) $sums->get(Transaction::TYPE_DEPOSIT, 0) / $scale, 2),
            // Withdrawals are stored negative; the panel reads them as an
            // amount that left, not as a negative number.
            'withdraw' => round(abs((float) $sums->get(Transaction::TYPE_WITHDRAW, 0)) / $scale, 2),
        ];
    }
}
