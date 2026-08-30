<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Section;
use App\Models\StudentSectionTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving a student from one group to another.
 *
 * The registration itself is moved rather than recreated, so the session
 * counter and the paid-through horizon continue from where they were — the
 * student does not start counting from zero — and the money already recorded
 * against the registration (and the share already credited to the previous
 * trainer for lessons actually taught) stays untouched.
 */
class StudentTransferService
{
    public static function transfer(
        Registration $registration,
        int $toSectionId,
        ?string $reason = null,
        ?int $performedBy = null,
    ): StudentSectionTransfer {
        $registration->loadMissing('section');
        $fromSectionId = (int) $registration->section_id;

        if ($fromSectionId === $toSectionId) {
            throw ValidationException::withMessages([
                'to_section_id' => __('The student is already enrolled in this section.'),
            ]);
        }

        $target = Section::find($toSectionId);

        if (! $target) {
            throw ValidationException::withMessages([
                'to_section_id' => __('Section not found.'),
            ]);
        }

        // Enforced here as well as in the picker: a transfer keeps the student's
        // session counter and paid-through horizon, which only means anything
        // within the same course.
        if ($registration->section && $target->subject_id !== $registration->section->subject_id) {
            throw ValidationException::withMessages([
                'to_section_id' => __('The new section must belong to the same course.'),
            ]);
        }

        // Someone who left is not moved, they are registered again. Carrying a
        // withdrawn registration across would land it in the new section still
        // marked as ended.
        if ($registration->hasLeft()) {
            throw ValidationException::withMessages([
                'to_section_id' => __('This student has withdrawn from the section. Undo the withdrawal first, or register them in the new section instead.'),
            ]);
        }

        $alreadyThere = Registration::query()
            ->where('student_id', $registration->student_id)
            ->where('section_id', $toSectionId)
            ->exists();

        if ($alreadyThere) {
            throw ValidationException::withMessages([
                'to_section_id' => __('The student is already enrolled in this section.'),
            ]);
        }

        // Students who withdrew freed their seats.
        if ($target->capacity && $target->registrations()->stillEnrolled()->count() >= $target->capacity) {
            throw ValidationException::withMessages([
                'to_section_id' => __('This section is full (capacity :capacity).', ['capacity' => $target->capacity]),
            ]);
        }

        return DB::transaction(function () use ($registration, $fromSectionId, $toSectionId, $target, $reason, $performedBy): StudentSectionTransfer {
            $transfer = StudentSectionTransfer::create([
                'student_id' => $registration->student_id,
                'from_section_id' => $fromSectionId,
                'to_section_id' => $toSectionId,
                'reason' => $reason,
                'transferred_by' => $performedBy ?? auth()->id(),
                'transferred_at' => now(),
            ]);

            // The student keeps their place in the current cycle: the sessions
            // counted in the old section become the new registration's starting
            // point, and it only starts collecting the new section's lessons
            // from the day of the move — the ones it held before the student
            // arrived are its own history, not their bill.
            $movedOn = now()->startOfDay();

            $registration->section_id = $toSectionId;
            $registration->sessions_carried_over = $target->isPerSessionBilled()
                ? (int) $registration->sessions_counted
                : 0;
            $registration->enrolled_at = $movedOn;
            $registration->session_offset = $target->isPerSessionBilled()
                ? SessionBillingService::heldSessionCountBefore($toSectionId, $movedOn)
                : 0;
            $registration->save();

            $registration->setRelation('section', $target);
            SessionBillingService::recount($registration);

            return $transfer;
        });
    }
}
