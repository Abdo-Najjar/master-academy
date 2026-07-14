<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Collection;

class FinancialDueService
{
    /**
     * Outstanding balance for a registration (course fee minus paid minus exemption).
     */
    public static function remainingBalance(Registration $registration): float
    {
        $due = (float) $registration->amount_due;
        $paid = (float) $registration->amount_paid;
        $exempt = (float) $registration->exemption_amount;

        return round($due - $paid - $exempt, 2);
    }

    /**
     * Compute financial status for a single (fixed-course) registration.
     *
     * ok       -> fully paid / exempted
     * due      -> partially paid, balance remaining
     * overdue  -> nothing paid yet on a course that has a fee
     */
    public static function computeStatus(Registration $registration): string
    {
        $remaining = self::remainingBalance($registration);

        if ($remaining <= 0.009) {
            return 'ok';
        }

        return ((float) $registration->amount_paid) <= 0.009 ? 'overdue' : 'due';
    }

    /**
     * Recalculate and persist financial_status for all registrations.
     * Returns the count of registrations whose status changed.
     */
    public static function refreshAllStatuses(bool $dryRun = false): int
    {
        $updated = 0;

        Registration::query()
            ->whereNull('deleted_at')
            ->chunk(200, function (Collection $registrations) use ($dryRun, &$updated): void {
                foreach ($registrations as $reg) {
                    $status = self::computeStatus($reg);
                    if ($reg->financial_status !== $status) {
                        $updated++;
                        if (! $dryRun) {
                            $reg->updateQuietly(['financial_status' => $status]);
                        }
                    }
                }
            });

        return $updated;
    }
}
