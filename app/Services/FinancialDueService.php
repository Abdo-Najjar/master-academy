<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialDueService
{
    /**
     * Total amount still owed by students: charges (`amount_paid`) that were
     * withdrawn from the student's wallet but not yet backed by real funds
     * (`funded_amount`) — i.e. the wallet went negative to cover them.
     * Unlike `financial_status`, this reflects actual money collected, not
     * just whether the bill was fully assigned.
     */
    public static function outstandingAmount(?Builder $query = null): float
    {
        $query ??= Registration::query()->whereNull('deleted_at');

        return (float) $query->get(['amount_paid', 'funded_amount'])->sum(
            fn (Registration $registration) => max(0.0, (float) $registration->amount_paid - (float) $registration->funded_amount)
        );
    }

    /**
     * Outstanding balance for a registration: the portion of the charge
     * (`amount_paid`) that isn't yet backed by real funds the student
     * deposited (`funded_amount`). `amount_paid` is withdrawn from the
     * student's wallet in full at registration time regardless of balance,
     * so it does not by itself indicate money was actually collected.
     */
    public static function remainingBalance(Registration $registration): float
    {
        $paid = (float) $registration->amount_paid;
        $funded = (float) $registration->funded_amount;

        return round(max(0.0, $paid - $funded), 2);
    }

    /**
     * Compute financial status for a single (fixed-course) registration.
     *
     * ok       -> fully funded by real money
     * due      -> partially funded, balance remaining
     * overdue  -> nothing funded yet on a charge that has a balance
     */
    public static function computeStatus(Registration $registration): string
    {
        $remaining = self::remainingBalance($registration);

        if ($remaining <= 0.009) {
            return 'ok';
        }

        return ((float) $registration->funded_amount) <= 0.009 ? 'overdue' : 'due';
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
