<?php

namespace App\Services;

use App\Models\Registration;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Leaving a section, as distinct from pausing one.
 *
 * A pause is a break: the student is coming back, they stay on the attendance
 * sheet, and their seat stays theirs. Leaving ends the registration on a date —
 * from that day they are off the sheet, off the bill, and the seat goes back
 * into the section's capacity. Nothing before that date is touched: the lessons
 * they attended, the money they paid and the trainer's share all stand.
 *
 * No money moves on its own. A student who paid ahead and then left may be owed
 * something, but whether that is refunded, carried to another section or kept
 * is the centre's call — the action only reports the number.
 */
class SectionWithdrawalService
{
    /** The student left this one section. */
    public const SOURCE_SECTION = 'section';

    /** The student left the centre, which closed this section with it. */
    public const SOURCE_CENTRE = 'centre';

    /**
     * End a registration on a given day (today unless it is being recorded
     * after the fact, which is the normal case when entering past registers).
     */
    public static function withdraw(
        Registration $registration,
        string|CarbonInterface|null $on = null,
        ?string $reason = null,
        string $source = self::SOURCE_SECTION,
    ): void {
        $day = self::asDate($on ?? now());

        DB::transaction(function () use ($registration, $day, $reason, $source): void {
            // A break that never ended is superseded by leaving outright;
            // closing it keeps the record from claiming both at once.
            $registration->pauses()->open()->update(['resumed_at' => $day]);

            $registration->forceFill([
                'left_at' => $day,
                'leave_reason' => $reason,
                'leave_source' => $source,
                'paused_at' => null,
            ])->saveQuietly();

            SessionBillingService::recount($registration);
        });
    }

    /**
     * Undo a withdrawal — the student came back, or it was recorded by mistake.
     * The lessons held while they were gone go back onto their bill, which is
     * the honest answer: as far as the record now goes, they never left.
     */
    public static function rejoin(Registration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $registration->forceFill([
                'left_at' => null,
                'leave_reason' => null,
                'leave_source' => null,
            ])->saveQuietly();

            SessionBillingService::recount($registration);
        });
    }

    protected static function asDate(string|CarbonInterface $date): string
    {
        return $date instanceof CarbonInterface
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();
    }
}
