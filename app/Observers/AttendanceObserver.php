<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\Registration;
use App\Services\AttendanceAlertService;
use App\Services\SessionBillingService;

class AttendanceObserver
{
    public function __construct(protected AttendanceAlertService $alerts) {}

    public function saved(Attendance $attendance): void
    {
        $attendance->loadMissing('section');
        if ($attendance->section) {
            $this->alerts->checkForSection(
                $attendance->section,
                [$attendance->student_id => $attendance->status]
            );
        }

        // A student who apologised is not charged for the lesson. Recount on
        // the way in *and* on the way out of "excused", so switching the status
        // back puts the lesson on their bill again.
        if ($attendance->wasChanged('status') || $attendance->wasRecentlyCreated) {
            $this->recountIfExcusable($attendance, [$attendance->status, $attendance->getOriginal('status')]);
        }
    }

    public function deleted(Attendance $attendance): void
    {
        $this->recountIfExcusable($attendance, [$attendance->status]);
    }

    /**
     * Recompute the student's counter for this section, but only when the
     * change could plausibly have moved it — i.e. "excused" was involved.
     *
     * @param  array<int, string|null>  $statuses
     */
    protected function recountIfExcusable(Attendance $attendance, array $statuses): void
    {
        if (! in_array(SessionBillingService::EXCUSED_STATUS, $statuses, true)) {
            return;
        }

        $registration = Registration::query()
            ->where('section_id', $attendance->section_id)
            ->where('student_id', $attendance->student_id)
            ->with('section')
            ->first();

        if ($registration?->isPerSessionBilled()) {
            SessionBillingService::recount($registration);

            // Taking an apology back puts the lesson on their bill again, which
            // can be the one that uses up the cycle.
            SessionBillingService::chargeSectionDueCycles((int) $attendance->section_id);
        }
    }
}
