<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the trainer's wallet credit for a registration in sync with how much
 * of that registration is actually backed by real student funds, instead of
 * crediting the trainer's full share the moment a registration is created
 * (even if the student's wallet went negative to cover it).
 */
class TrainerPayoutService
{
    /**
     * Move `$registration`'s funded amount to `$newFundedAmount` (clamped to
     * [0, amount_paid]) and deposit/withdraw the trainer's wallet for the
     * resulting change in their credited share.
     */
    public static function applyFundedDelta(Registration $registration, float $newFundedAmount): void
    {
        $registration->loadMissing(['section.trainer', 'section.subject', 'student']);
        $trainer = $registration->section?->trainer;

        $amountPaid = (float) $registration->amount_paid;
        $trainerAmount = (float) $registration->trainer_amount;
        $currentCredited = (float) $registration->trainer_credited_amount;

        $newFundedAmount = max(0.0, min($amountPaid, $newFundedAmount));
        $rate = $amountPaid > 0 ? $trainerAmount / $amountPaid : 0.0;
        $targetCredited = round($newFundedAmount * $rate, 2);
        $trainerDelta = round($targetCredited - $currentCredited, 2);

        if (abs($trainerDelta) < 0.005 && abs($newFundedAmount - (float) $registration->funded_amount) < 0.005) {
            return;
        }

        $locale = app()->getLocale();
        $studentName = $registration->student?->getTranslation('name', $locale, false) ?? '#'.$registration->student_id;
        $sectionName = $registration->section?->name ?? '#'.$registration->section_id;
        $courseName = $registration->section?->subject?->getTranslation('name', $locale, false);

        // The trainer only sees the description column in their portal, so it
        // carries who paid and which course/section the share came from.
        $target = $courseName ? $courseName.' — '.__('Section').' '.$sectionName : $sectionName;

        if ($trainer && $trainerDelta > 0) {
            $trainer->depositFloat($trainerDelta, [
                'description' => __('Trainer share from :student — :name', ['student' => $studentName, 'name' => $target]),
                'note' => __('Registration #:id — :student', ['id' => $registration->id, 'student' => $studentName]),
            ]);
        } elseif ($trainer && $trainerDelta < 0) {
            $trainer->forceWithdrawFloat(abs($trainerDelta), [
                'description' => __('Trainer share adjustment from :student — :name', ['student' => $studentName, 'name' => $target]),
                'note' => __('Registration #:id — :student', ['id' => $registration->id, 'student' => $studentName]),
            ]);
        }

        $registration->forceFill([
            'funded_amount' => $newFundedAmount,
            'trainer_credited_amount' => $targetCredited,
        ]);

        $registration->forceFill([
            'financial_status' => FinancialDueService::computeStatus($registration),
        ])->saveQuietly();
    }

    /**
     * When a student's wallet is topped up, settle the trainer's pending
     * share for their oldest under-funded registrations first (FIFO), up to
     * the amount just deposited.
     */
    public static function settleForStudent(Student $student, float $availableBudget): void
    {
        if ($availableBudget <= 0) {
            return;
        }

        DB::transaction(function () use ($student, $availableBudget): void {
            $budget = $availableBudget;

            $pending = Registration::query()
                ->where('student_id', $student->id)
                ->whereColumn('funded_amount', '<', 'amount_paid')
                ->orderBy('created_at')
                ->get();

            foreach ($pending as $registration) {
                if ($budget <= 0) {
                    break;
                }

                $remaining = round((float) $registration->amount_paid - (float) $registration->funded_amount, 2);
                if ($remaining <= 0) {
                    continue;
                }

                $apply = min($budget, $remaining);
                self::applyFundedDelta($registration, (float) $registration->funded_amount + $apply);
                $budget = round($budget - $apply, 2);
            }
        });
    }
}
