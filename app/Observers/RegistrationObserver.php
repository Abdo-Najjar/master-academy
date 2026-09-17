<?php

namespace App\Observers;

use App\Models\Registration;
use App\Models\Section;
use App\Services\FinancialDueService;
use App\Services\SessionBillingService;
use App\Services\TrainerPayoutService;
use Illuminate\Support\Facades\DB;

class RegistrationObserver
{
    /**
     * Money columns that are NOT NULL with a zero default. Every screen offers
     * them as an optional box, and an emptied box arrives here as null — which
     * the database rejects outright. Blank means "nothing", so it is written as
     * a zero. Runs before `creating`/`updating`, which read these as floats.
     *
     * @var list<string>
     */
    private const ZERO_IF_BLANK = [
        'amount_due',
        'amount_paid',
        'exemption_amount',
        'trainer_amount',
        'session_offset',
        'sessions_carried_over',
        'sessions_counted',
        'paid_through_session',
    ];

    public function saving(Registration $registration): void
    {
        foreach (self::ZERO_IF_BLANK as $column) {
            if ($registration->getAttribute($column) === null) {
                $registration->setAttribute($column, 0);
            }
        }
    }

    /**
     * On creating: if the trainer's share wasn't provided (e.g. Quick Enroll or
     * the admin form, which don't expose the field), derive it from the
     * section's effective rate applied to the amount paid.
     */
    public function creating(Registration $registration): void
    {
        // Every registration needs a day it started from — attendance sheets,
        // reports and per-session billing all read it. Fall back to the
        // student's own enrolment date before today, so a backdated student
        // does not silently get a today-dated registration.
        if (! $registration->enrolled_at) {
            $registration->loadMissing('student');
            $registration->enrolled_at = $registration->student?->enrolled_at ?? now()->startOfDay();
        }

        if (empty($registration->trainer_amount) || (float) $registration->trainer_amount === 0.0) {
            $section = $registration->section ?: Section::find($registration->section_id);
            if ($section) {
                $registration->trainer_amount = round(
                    ((float) $registration->amount_paid) * $section->effectiveTrainerRate() / 100,
                    2
                );
            }
        }

        SessionBillingService::initializeRegistration($registration);

        $registration->financial_status = FinancialDueService::computeStatus($registration);
    }

    /**
     * Keep financial_status in sync whenever the money fields change — or, on
     * per-session sections, whenever the session counters move.
     */
    public function updating(Registration $registration): void
    {
        // The trainer's share is stored as an amount but read back as a rate
        // (`trainer_amount / amount_paid`), so it has to move with the charge.
        // Left alone, correcting a charge downwards leaves the trainer holding
        // more than the student ever paid, and correcting it upwards — or
        // collecting further cycles — quietly cuts them below their percentage.
        // A share set explicitly in the same save wins: that is a deliberate
        // rate correction, not a side effect.
        if ($registration->isDirty('amount_paid') && ! $registration->isDirty('trainer_amount')) {
            $section = $registration->section ?: Section::find($registration->section_id);

            if ($section) {
                $registration->trainer_amount = round(
                    ((float) $registration->amount_paid) * $section->effectiveTrainerRate() / 100,
                    2
                );
            }
        }

        if ($registration->isDirty(['amount_due', 'amount_paid', 'exemption_amount', 'sessions_counted', 'paid_through_session', 'paused_at'])) {
            $registration->financial_status = FinancialDueService::computeStatus($registration);
        }
    }

    /**
     * On create: deduct `amount_paid` from the student's wallet (allows
     * negative balance). The trainer is only credited for the portion of
     * that charge the student's wallet actually had available *before* this
     * charge — not for money the student doesn't yet have. Any uncredited
     * remainder is settled automatically later, when the student's wallet is
     * topped up (see TrainerPayoutService::settleForStudent()).
     */
    public function created(Registration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $registration->loadMissing(['student', 'section.trainer']);
            $student = $registration->student;

            $amountPaid = (float) $registration->amount_paid;
            $balanceBefore = $student ? $student->balanceFloat : 0.0;

            if ($student && $amountPaid > 0) {
                $student->forceWithdrawFloat($amountPaid, [
                    'description' => __('Charge for :student — :name', [
                        'student' => $registration->studentLabel(),
                        'name' => $registration->sectionLabel(),
                    ]),
                    'note' => $registration->contextLabel(),
                    'payment_type_id' => $registration->payment_type_id,
                ]);
            }

            $covered = max(0.0, min($amountPaid, $balanceBefore));
            TrainerPayoutService::applyFundedDelta($registration, $covered);
        });
    }

    /**
     * On update: only adjust the delta between old and new amount_paid.
     * Withdraw the extra if it went up (crediting the trainer only for the
     * portion the student could actually cover), refund the difference if it
     * went down (clawing back any trainer credit above the new, lower cap).
     * A direct edit to trainer_amount (e.g. a rate correction) is also
     * re-settled against the registration's current funded amount.
     */
    public function updated(Registration $registration): void
    {
        // Correcting the day a student joined — or the day they left — re-decides
        // which of the section's lessons were ever theirs to pay for.
        if ($registration->wasChanged(['enrolled_at', 'left_at'])) {
            SessionBillingService::recount($registration);
        }

        $changedPaid = $registration->wasChanged('amount_paid');
        $changedTrainer = $registration->wasChanged('trainer_amount');

        if (! $changedPaid && ! $changedTrainer) {
            return;
        }

        DB::transaction(function () use ($registration, $changedPaid): void {
            $registration->loadMissing(['student', 'section.trainer']);
            $student = $registration->student;

            $targetFunded = (float) $registration->funded_amount;

            if ($changedPaid && $student) {
                $old = (float) $registration->getOriginal('amount_paid');
                $new = (float) $registration->amount_paid;
                $diff = $new - $old;

                if ($diff > 0) {
                    $balanceBefore = $student->balanceFloat;
                    $student->forceWithdrawFloat($diff, [
                        'description' => __('Additional charge for :student — :name', [
                            'student' => $registration->studentLabel(),
                            'name' => $registration->sectionLabel(),
                        ]),
                        'note' => $registration->contextLabel(),
                        'payment_type_id' => $registration->payment_type_id,
                    ]);
                    $targetFunded += max(0.0, min($diff, $balanceBefore));
                } elseif ($diff < 0) {
                    $student->depositFloat(abs($diff), [
                        'description' => __('Adjustment for :student — :name', [
                            'student' => $registration->studentLabel(),
                            'name' => $registration->sectionLabel(),
                        ]),
                        'note' => $registration->contextLabel(),
                    ]);
                }
            }

            TrainerPayoutService::applyFundedDelta($registration, $targetFunded);
        });
    }

    /**
     * No automatic refund on plain delete. Use the "Cancel & Refund" action
     * (Registration::deleteWithWalletAdjustments) when you want the money
     * returned to the student.
     */
    public function deleted(Registration $registration): void
    {
        //
    }
}
