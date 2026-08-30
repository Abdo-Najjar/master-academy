<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Which course is this money for?"
 *
 * A student sitting in three courses has three separate bills but a single
 * wallet balance, so a plain deposit could only be spread FIFO — oldest
 * under-funded registration first (TrainerPayoutService::settleForStudent).
 * The desk had no way to say "the 50 he just handed over is for maths, not for
 * english", and the student's own screen showed one lump balance with no clue
 * which course it came from.
 *
 * This service answers both questions per registration: how much each course
 * needs right now, and what it means to put a given amount onto one of them.
 */
class PaymentAllocationService
{
    /**
     * Every course of this student that wants money right now, oldest first —
     * the order the desk is asked to settle them in.
     *
     * @return Collection<int, Registration>
     */
    public static function outstandingFor(Student $student): Collection
    {
        return Registration::query()
            ->where('student_id', $student->getKey())
            ->with(['section.subject', 'section.branch'])
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Registration $registration): bool => self::amountDueNow($registration) > 0.009)
            ->values();
    }

    /**
     * What this one course is owed today: the part of its bill no real money
     * backs yet, plus — on per-session sections that are collected by hand —
     * the cycles the student has already used up but was never billed for.
     */
    public static function amountDueNow(Registration $registration): float
    {
        return round(
            FinancialDueService::remainingBalance($registration) + self::unbilledCycleValue($registration),
            2
        );
    }

    /**
     * Money for cycles that have been consumed but never charged.
     *
     * Sections that charge their own cycles normally have none of these — the
     * horizon is already ahead of the counter, so `outstandingCycles()` answers
     * zero on its own. It is the abnormal case this covers: auto-charging turned
     * on after a backlog had built up, or lessons entered while charging was
     * suspended, leave the horizon behind and the course reading "overdue" with
     * no bill anywhere to pay. Collecting has to close those either way.
     */
    public static function unbilledCycleValue(Registration $registration): float
    {
        $section = $registration->section;

        if (! $section?->isPerSessionBilled()) {
            return 0.0;
        }

        return round(self::outstandingCycles($registration) * (float) $section->cycle_fee, 2);
    }

    /**
     * Cycles the student owes on a per-session course — the same arithmetic
     * SessionBillingService::chargeDueCycles() uses to decide what to charge, so
     * collecting by hand lands on exactly what auto-charging would have taken.
     */
    public static function outstandingCycles(Registration $registration): int
    {
        $section = $registration->section;

        // Leaving closes the account: cycles that would start after the student
        // walked out are not theirs to pay for.
        if (! $section?->isPerSessionBilled() || $registration->left_at) {
            return 0;
        }

        $perCycle = (int) $section->sessions_per_cycle;

        // Nothing consumed yet means no cycle has closed, so there is nothing to
        // collect however far ahead the horizon sits.
        if ($perCycle <= 0 || (int) $registration->sessions_counted <= 0) {
            return 0;
        }

        $outstanding = (int) $registration->sessions_counted - (int) $registration->paid_through_session;

        if ($outstanding < 0) {
            return 0;
        }

        return intdiv($outstanding, $perCycle) + 1;
    }

    /**
     * Spread an already-banked deposit over the courses that owe, oldest bill
     * first — what happens when the desk names no course at all.
     *
     * TrainerPayoutService::settleForStudent() used to do this, but it only ever
     * moves `funded_amount`. On a per-session course whose used-up cycles were
     * never billed there is no `amount_paid` to fund, so it would find nothing
     * to settle and the money would sit as credit against a course still
     * reading "overdue". Going through `apply()` closes those cycles, which
     * makes the automatic path settle exactly what naming the course by hand
     * would have.
     *
     * @return float how much of `$amount` the courses took between them
     */
    public static function autoApply(Student $student, float $amount): float
    {
        $budget = round($amount, 2);
        $applied = 0.0;

        foreach (self::outstandingFor($student) as $registration) {
            if ($budget <= 0.009) {
                break;
            }

            $share = min($budget, self::amountDueNow($registration));
            $took = self::apply($registration, $share);

            $applied = round($applied + $took, 2);
            $budget = round($budget - $share, 2);
        }

        return $applied;
    }

    /**
     * Put `$amount` of an already-banked deposit onto one course.
     *
     * The money must be on the wallet *before* this runs: buying a cycle raises
     * the bill, and RegistrationObserver settles a fresh charge out of whatever
     * the wallet holds at that moment.
     *
     * @return float how much of `$amount` the course could actually take
     */
    public static function apply(Registration $registration, float $amount): float
    {
        $amount = round($amount, 2);

        if ($amount <= 0.009) {
            return 0.0;
        }

        return DB::transaction(function () use ($registration, $amount): float {
            $registration->loadMissing('section');
            $fundedBefore = (float) $registration->funded_amount;

            self::buyCycles($registration, $amount);

            // Never walk the funded amount back: a charge the observer settled
            // out of existing wallet credit is genuinely paid, even if it went
            // past what this allocation covers.
            $target = max(
                (float) $registration->funded_amount,
                min((float) $registration->amount_paid, $fundedBefore + $amount)
            );

            TrainerPayoutService::applyFundedDelta($registration, $target);

            return round((float) $registration->funded_amount - $fundedBefore, 2);
        });
    }

    /**
     * Close as many used-up cycles as this allocation pays for.
     *
     * Without it the money would land as plain wallet credit against a bill that
     * was never raised, and the course would keep reading "overdue" no matter
     * how much the student paid — the per-session status is driven by the
     * paid-through horizon, and only charging a cycle moves it.
     */
    protected static function buyCycles(Registration $registration, float $amount): void
    {
        $section = $registration->section;

        if (! $section?->isPerSessionBilled()) {
            return;
        }

        $cycleFee = (float) $section->cycle_fee;

        if ($cycleFee <= 0) {
            return;
        }

        // Whole cycles only — half a cycle's money buys no sessions, it just
        // sits against the next bill.
        $cycles = min(
            self::outstandingCycles($registration),
            (int) floor(($amount + 0.009) / $cycleFee)
        );

        if ($cycles > 0) {
            SessionBillingService::payCycle($registration, $cycles);
            $registration->refresh();
        }
    }
}
