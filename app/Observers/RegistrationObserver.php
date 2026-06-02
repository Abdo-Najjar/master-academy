<?php

namespace App\Observers;

use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class RegistrationObserver
{
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

            if ($student && $amountPaid > 0) {
                $student->forceWithdrawFloat($amountPaid, [
                    'description' => __('Charge for registration: :name', [
                        'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                    ]),
                    'note' => __('Registration #:id', ['id' => $registration->id]),
                    'payment_type_id' => $registration->payment_type_id,
                ]);
            }

            if ($trainer && $trainerAmount > 0) {
                $trainer->depositFloat($trainerAmount, [
                    'description' => __('Trainer share for registration: :name', [
                        'name' => $registration->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$registration->section_id,
                    ]),
                    'note' => __('Registration #:id', ['id' => $registration->id]),
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
