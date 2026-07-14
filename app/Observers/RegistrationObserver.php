<?php

namespace App\Observers;

use App\Models\Registration;
use App\Models\Section;
use App\Services\FinancialDueService;
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
     * On create: deduct `amount_paid` from the student's wallet (allows negative
     * balance) and credit `trainer_amount` to the trainer's wallet.
     */
    public function created(Registration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $registration->loadMissing(['student', 'section.trainer']);
            $student = $registration->student;
            $trainer = $registration->section?->trainer;

            $amountPaid = (float) $registration->amount_paid;
            $trainerAmount = (float) $registration->trainer_amount;
            $studentName = $student?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->student_id;

            if ($student && $amountPaid > 0) {
                $student->forceWithdrawFloat($amountPaid, [
                    'description' => __('Charge for registration: :name', [
                        'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                    ]),
                    'note' => __('Registration #:id — :student', ['id' => $registration->id, 'student' => $studentName]),
                    'payment_type_id' => $registration->payment_type_id,
                ]);
            }

            if ($trainer && $trainerAmount > 0) {
                $trainer->depositFloat($trainerAmount, [
                    'description' => __('Trainer share for registration: :name', [
                        'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                    ]),
                    'note' => __('Registration #:id — :student', ['id' => $registration->id, 'student' => $studentName]),
                ]);
            }
        });
    }

    /**
     * On update: only adjust the delta between old and new amounts. Withdraw
     * the extra if amount_paid went up, refund the difference if it went down.
     * Same logic for trainer_amount.
     */
    public function updated(Registration $registration): void
    {
        // Only act if money fields actually changed
        $changedPaid = $registration->wasChanged('amount_paid');
        $changedTrainer = $registration->wasChanged('trainer_amount');

        if (! $changedPaid && ! $changedTrainer) {
            return;
        }

        DB::transaction(function () use ($registration, $changedPaid, $changedTrainer): void {
            $registration->loadMissing(['student', 'section.trainer']);
            $student = $registration->student;
            $trainer = $registration->section?->trainer;

            if ($changedPaid && $student) {
                $old = (float) $registration->getOriginal('amount_paid');
                $new = (float) $registration->amount_paid;
                $diff = $new - $old;

                if ($diff > 0) {
                    $student->forceWithdrawFloat($diff, [
                        'description' => __('Additional charge for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
                        'payment_type_id' => $registration->payment_type_id,
                    ]);
                } elseif ($diff < 0) {
                    $student->depositFloat(abs($diff), [
                        'description' => __('Adjustment for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
                    ]);
                }
            }

            if ($changedTrainer && $trainer) {
                $old = (float) $registration->getOriginal('trainer_amount');
                $new = (float) $registration->trainer_amount;
                $diff = $new - $old;

                if ($diff > 0) {
                    $trainer->depositFloat($diff, [
                        'description' => __('Additional trainer share for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
                    ]);
                } elseif ($diff < 0) {
                    $trainer->forceWithdrawFloat(abs($diff), [
                        'description' => __('Trainer share adjustment for registration: :name', [
                            'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                        ]),
                        'note' => __('Registration #:id', ['id' => $registration->id]),
                    ]);
                }
            }
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
