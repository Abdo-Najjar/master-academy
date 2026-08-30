<?php

namespace App\Observers;

use App\Models\Registration;
use App\Models\Student;
use App\Services\SectionWithdrawalService;

class StudentObserver
{
    /** Statuses that mean the student is no longer attending anything. */
    protected const GONE_STATUSES = ['withdrawn', 'archived'];

    public function creating(Student $student): void
    {
        if (empty($student->student_number)) {
            do {
                $number = 'STU-'.random_int(100000, 999999);
            } while (Student::query()->withTrashed()->where('student_number', $number)->exists());

            $student->student_number = $number;
        }
    }

    /**
     * Leaving the centre means leaving every section in it. Without this the
     * student disappears from the front of the system while their sections
     * quietly keep counting lessons — and billing them — behind it.
     */
    public function updated(Student $student): void
    {
        if (! $student->wasChanged('status')) {
            return;
        }

        $registrations = Registration::query()
            ->where('student_id', $student->getKey())
            ->with('section')
            ->get();

        if (in_array($student->status, self::GONE_STATUSES, true)) {
            $on = $student->withdrawal_date ?? now();

            foreach ($registrations->whereNull('left_at') as $registration) {
                SectionWithdrawalService::withdraw(
                    $registration,
                    $on,
                    $student->withdrawal_reason ?: __('Withdrawn from the centre'),
                    SectionWithdrawalService::SOURCE_CENTRE,
                );
            }

            return;
        }

        // Coming back re-opens only what leaving the centre closed. A section
        // the student had dropped on their own stays dropped.
        if ($student->status === 'active') {
            $closedByCentre = $registrations
                ->whereNotNull('left_at')
                ->where('leave_source', SectionWithdrawalService::SOURCE_CENTRE);

            foreach ($closedByCentre as $registration) {
                SectionWithdrawalService::rejoin($registration);
            }
        }
    }
}
