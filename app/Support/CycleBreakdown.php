<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\Section;
use Illuminate\Support\Number;

/**
 * The same money, said in the unit the desk negotiates in.
 *
 * A per-session course is sold as "60 a month" and every conversation about it
 * is counted in months — how many were taught, how many were paid, how many are
 * owed. The balance screen only ever showed the totals, so "350 charged, 230
 * paid, 120 left" made whoever read it divide by the cycle fee in their head
 * before they could answer the one question a parent actually asks: how many
 * months am I behind?
 *
 * Everything here is a caption for an amount that is already on screen, so a
 * course not priced by cycle simply has nothing to add and gets null.
 */
final class CycleBreakdown
{
    /**
     * "شهران × ٦٠٫٠٠ ₪" for an amount on a course priced per cycle, or null
     * when there is nothing to break down.
     *
     * A part-paid course leaves a remainder that buys no whole month — it is
     * shown as itself rather than rounded away, so the three captions always
     * add back up to the totals beside them.
     */
    public static function forAmount(?Section $section, float $amount): ?string
    {
        if (! $section?->isPerSessionBilled()) {
            return null;
        }

        $fee = (float) $section->cycle_fee;

        if ($fee <= 0 || $amount <= 0.009) {
            return null;
        }

        $months = (int) floor(($amount + 0.009) / $fee);
        $remainder = round($amount - ($months * $fee), 2);

        $parts = [];

        if ($months > 0) {
            $parts[] = self::months($months).' × '.self::money($fee);
        }

        if ($remainder > 0.009) {
            $parts[] = self::money($remainder);
        }

        return $parts === [] ? null : implode(' + ', $parts);
    }

    /**
     * The caption for what a course still owes.
     *
     * Told apart from the other two because the debt is the line people act on:
     * it names the lessons behind the number as well, since on a per-session
     * course "two months" is really "the sixteen lessons already taught".
     */
    public static function forOutstanding(Registration $registration, float $amount): ?string
    {
        $section = $registration->section;
        $caption = self::forAmount($section, $amount);

        if ($caption === null) {
            return null;
        }

        $perCycle = (int) $section->sessions_per_cycle;
        $unpaidSessions = max(0, (int) $registration->sessions_counted - (int) $registration->paid_through_session);

        // Only worth saying while the counter is actually past the paid
        // horizon; a course paid up to date owes money for months it has not
        // taught yet, and quoting zero lessons against it reads as a bug.
        if ($perCycle <= 0 || $unpaidSessions <= 0) {
            return $caption;
        }

        return $caption.' · '.__(':count unpaid sessions', [
            'count' => Number::format($unpaidSessions, locale: app()->getLocale()),
        ]);
    }

    /**
     * "شهر واحد" / "شهران" / "٣ شهور" — Arabic counts months its own way, and
     * the digits are localised separately from the choice so the number matches
     * the amount it stands next to.
     */
    public static function months(int $count): string
    {
        return trans_choice(':count month|:count months', $count, [
            'count' => Number::format($count, locale: app()->getLocale()),
        ]);
    }

    /**
     * Formatted the way the amount directly above it is — these captions sit
     * under `->money('ILS')` entries, and Latin digits under Arabic-Indic ones
     * read as two different currencies.
     */
    private static function money(float $amount): string
    {
        return Number::currency($amount, 'ILS', app()->getLocale());
    }
}
