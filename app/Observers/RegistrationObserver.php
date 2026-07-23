<?php

namespace App\Observers;

use App\Models\Registration;
use App\Models\Section;
use App\Services\FinancialDueService;
use App\Services\TrainerPayoutService;
use Illuminate\Support\Facades\DB;

class RegistrationObserver
{
    /**
     * On creating: if the trainer's share wasn't provided (e.g. Quick Enroll or
     * the admin form, which don't expose the field), derive it from the
     * section's effective rate applied to the amount paid.
     */
    public function creating(Registration $registration): void
    {
        if (empty($registration->trainer_amount) || (float) $registration->trainer_amount === 0.0) {
            $section = $registration->section ?: Section::find($registration->section_id);
            if ($section) {
                $registration->trainer_amount = round(
                    ((float) $registration->amount_paid) * $section->effectiveTrainerRate() / 100,
                    2
                );
            }
        }

        $registration->financial_status = FinancialDueService::computeStatus($registration);
    }

    /**
     * Keep financial_status in sync whenever the money fields change.
     */
    public function updating(Registration $registration): void
    {
        if ($registration->isDirty(['amount_due', 'amount_paid', 'exemption_amount'])) {
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
            $studentName = $student?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->student_id;
            $balanceBefore = $student ? $student->balanceFloat : 0.0;

            if ($student && $amountPaid > 0) {
                $student->forceWithdrawFloat($amountPaid, [
                    'description' => __('Charge for registration: :name', [
                        'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                    ]),
                    'note' => __('Registration #:id — :student', ['id' => $registration->id, 'student' => $studentName]),
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
                        'description' => __('Additional charge for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
                        'payment_type_id' => $registration->payment_type_id,
                    ]);
                    $targetFunded += max(0.0, min($diff, $balanceBefore));
                } elseif ($diff < 0) {
                    $student->depositFloat(abs($diff), [
                        'description' => __('Adjustment for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
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
