<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\Section;

/**
 * What a section is worth, what came in, and what is still owed on it.
 *
 * The three figures the desk asks for — expected, collected, remaining — are
 * spread across four columns of `registrations`, and each one means something
 * narrower than its name suggests:
 *
 * - `amount_due` is the list price *before* the discount, so summing it
 *   overstates every exempted student.
 * - `amount_paid` is the net charge actually raised (due minus exemption). It
 *   is withdrawn from the student's wallet in full the moment they enrol,
 *   whether or not they handed anything over — so it is what the section is
 *   owed, not what it earned.
 * - `funded_amount` is the part of that charge backed by real money.
 * - the gap between the two is the debt.
 *
 * A fixed-course section raises its whole price at enrolment, so "expected" is
 * the course's full value. A per-session section raises one cycle at a time, so
 * "expected" is what has been billed so far and climbs with every cycle the
 * section runs — there is no honest total for a course whose length nobody has
 * decided yet.
 */
final class SectionFinancials
{
    private function __construct(
        /** Net charges raised on the section, after exemptions. */
        public readonly float $expected,
        /** Of those charges, the part backed by money students actually paid. */
        public readonly float $collected,
        /** Charges still unbacked — what the section is owed right now. */
        public readonly float $outstanding,
        /** Discounts granted, which is why `expected` is below the list price. */
        public readonly float $exemptions,
        /** The trainer's full share of those charges. */
        public readonly float $trainerShare,
        /** The part of that share already deposited in the trainer's wallet. */
        public readonly float $trainerCredited,
        /** Registrations behind the figures, leavers included — they still owe. */
        public readonly int $registrations,
    ) {}

    public static function for(Section $section): self
    {
        // `toBase()` keeps the soft-delete and branch scopes but skips model
        // hydration: this is one aggregate row, not a registration.
        $row = Registration::query()
            ->where('section_id', $section->getKey())
            ->reportable()
            ->toBase()
            ->selectRaw('COUNT(*) as registrations')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as expected')
            ->selectRaw('COALESCE(SUM(funded_amount), 0) as collected')
            ->selectRaw('COALESCE(SUM(exemption_amount), 0) as exemptions')
            ->selectRaw('COALESCE(SUM(trainer_amount), 0) as trainer_share')
            ->selectRaw('COALESCE(SUM(trainer_credited_amount), 0) as trainer_credited')
            // Summed per registration rather than as a difference of the two
            // totals. They agree only while funding stays clamped to the charge
            // it backs, which is TrainerPayoutService's invariant, not this
            // query's — and a section's debt should not depend on it holding.
            ->selectRaw('COALESCE(SUM(CASE WHEN amount_paid > funded_amount THEN amount_paid - funded_amount ELSE 0 END), 0) as outstanding')
            ->first();

        return new self(
            expected: round((float) ($row->expected ?? 0), 2),
            collected: round((float) ($row->collected ?? 0), 2),
            outstanding: round((float) ($row->outstanding ?? 0), 2),
            exemptions: round((float) ($row->exemptions ?? 0), 2),
            trainerShare: round((float) ($row->trainer_share ?? 0), 2),
            trainerCredited: round((float) ($row->trainer_credited ?? 0), 2),
            registrations: (int) ($row->registrations ?? 0),
        );
    }

    /**
     * What the centre keeps: money in hand, less the share already handed to
     * the trainer. Both sides are the *collected* figures — an uncollected
     * charge has not made the centre anything to keep.
     */
    public function net(): float
    {
        return round($this->collected - $this->trainerCredited, 2);
    }

    /** How much of what was billed has been collected, 0–100. */
    public function collectionRate(): float
    {
        if ($this->expected <= 0.009) {
            return 0.0;
        }

        return round($this->collected / $this->expected * 100, 1);
    }
}
