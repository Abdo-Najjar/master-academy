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

        $alreadyThere = Registration::query()
            ->where('student_id', $registration->student_id)
            ->where('section_id', $toSectionId)
            ->exists();

        if ($alreadyThere) {
            throw ValidationException::withMessages([
                'to_section_id' => __('The student is already enrolled in this section.'),
            ]);
        }

        if ($target->capacity && $target->registrations()->count() >= $target->capacity) {
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

            // The counter carries over as-is; only the offset is re-based onto
            // the new section so a future recount stays meaningful there.
            $registration->section_id = $toSectionId;
            $registration->session_offset = $target->isPerSessionBilled()
                ? SessionBillingService::heldSessionCount($toSectionId)
                : 0;
            $registration->save();

            return $transfer;
        });
    }
}
