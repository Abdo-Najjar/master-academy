<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialDueService
{
    /**
     * Count unique session dates in a section AFTER a given date (inclusive).
     */
    public static function countSectionSessionsAfter(int $sectionId, string $afterDate): int
    {
        return DB::table('attendances')
            ->where('section_id', $sectionId)
            ->where('date', '>=', $afterDate)
            ->distinct('date')
            ->count('date');
    }

    /**
     * Count total unique session dates in a section up to now.
     */
    public static function countTotalSectionSessions(int $sectionId): int
    {
        return DB::table('attendances')
            ->where('section_id', $sectionId)
            ->distinct('date')
            ->count('date');
    }

    /**
     * Compute current session count for a registration (sessions since student registered).
     */
    public static function currentSessionCount(Registration $registration): int
    {
        if (! $registration->created_at) {
            return 0;
        }

        return self::countSectionSessionsAfter(
            $registration->section_id,
            $registration->created_at->toDateString()
        );
    }

    /**
     * Sessions since last payment for a registration.
     */
    public static function sessionsSinceLastPayment(Registration $registration): int
    {
        $total = self::currentSessionCount($registration);
        $paidThrough = (int) $registration->paid_through_session;

        return max(0, $total - $paidThrough);
    }

    /**
     * Compute financial status for a single registration.
     * ok | warning (2 sessions before due) | due | overdue
     */
    public static function computeStatus(Registration $registration): string
    {
        $section = $registration->section;
        if (! $section || $section->fee_type !== 'per_session') {
            return 'ok';
        }

        $cycle = (int) ($section->sessions_per_fee_cycle ?? 0);
        if ($cycle <= 0) {
            return 'ok';
        }

        $sessionsSince = self::sessionsSinceLastPayment($registration);

        if ($sessionsSince >= $cycle + 2) {
            return 'overdue';
        }
        if ($sessionsSince >= $cycle) {
            return 'due';
        }
        if ($sessionsSince >= $cycle - 2) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Update financial_status for all per_session registrations.
     * Returns the count of registrations that were (or would be) updated.
     */
    public static function refreshAllStatuses(bool $dryRun = false): int
    {
        $updated = 0;

        Registration::query()
            ->whereNull('deleted_at')
            ->whereHas('section', fn ($q) => $q->where('fee_type', 'per_session'))
            ->with('section')
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

    /**
     * Mark payment received: advance paid_through_session by one cycle.
     */
    public static function recordPayment(Registration $registration): void
    {
        $section = $registration->section;
        $cycle = (int) ($section?->sessions_per_fee_cycle ?? 0);

        if ($cycle <= 0) {
            return;
        }

        $newPaid = (int) $registration->paid_through_session + $cycle;
        $registration->update([
            'paid_through_session' => $newPaid,
            'financial_status' => 'ok',
        ]);
    }
}
