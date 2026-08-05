<?php

namespace App\Services;

use App\Models\Section;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Derives a section's meeting schedule from its date range and weekly times,
 * and reconciles it against the attendance that was actually recorded — so a
 * trainer can see which sessions already happened and how many are left.
 */
class SectionScheduleService
{
    /** Safety valve: never expand more than ~3 years of a mis-entered date range. */
    protected const MAX_DAYS = 1100;

    /**
     * Every date the section is scheduled to meet, as `Y-m-d` strings.
     *
     * @return Collection<int, string>
     */
    public static function plannedDates(Section $section): Collection
    {
        $section->loadMissing('times');

        $weekdays = $section->times
            ->pluck('day')
            ->map(fn (?string $day) => strtolower((string) $day))
            ->filter()
            ->unique()
            ->all();

        if ($weekdays === [] || ! $section->start_date || ! $section->end_date) {
            return collect();
        }

        $cursor = CarbonImmutable::parse($section->start_date)->startOfDay();
        $end = CarbonImmutable::parse($section->end_date)->startOfDay();

        if ($end->lt($cursor)) {
            return collect();
        }

        $dates = collect();
        for ($i = 0; $i <= self::MAX_DAYS && $cursor->lte($end); $i++) {
            if (in_array(strtolower($cursor->format('l')), $weekdays, true)) {
                $dates->push($cursor->toDateString());
            }
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Dates that already have attendance recorded, newest first, each with the
     * per-status tally for that day.
     *
     * @return Collection<int, array{date: string, present: int, absent: int, late: int, excused: int, total: int}>
     */
    public static function heldSessions(Section $section): Collection
    {
        $section->loadMissing('attendances');

        return $section->attendances
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->date)->toDateString())
            ->sortKeysDesc()
            ->map(fn (Collection $rows, string $date) => [
                'date' => $date,
                'present' => $rows->where('status', 'present')->count(),
                'absent' => $rows->where('status', 'absent')->count(),
                'late' => $rows->where('status', 'late')->count(),
                'excused' => $rows->where('status', 'excused')->count(),
                'total' => $rows->count(),
            ])
            ->values();
    }

    /**
     * Held / planned / remaining counts plus the next scheduled date.
     *
     * Remaining counts only planned dates with no attendance yet, so an ad-hoc
     * make-up session never pushes the remaining count negative.
     *
     * @return array{held: int, planned: int, remaining: int, next_date: ?string, held_dates: Collection<int, array<string, mixed>>}
     */
    public static function summary(Section $section): array
    {
        $planned = self::plannedDates($section);
        $held = self::heldSessions($section);
        $heldDates = $held->pluck('date')->all();

        $outstanding = $planned->reject(fn (string $date) => in_array($date, $heldDates, true));
        $today = CarbonImmutable::now()->toDateString();

        return [
            'held' => $held->count(),
            // A make-up session outside the weekly pattern still counts toward the total.
            'planned' => $planned->merge($heldDates)->unique()->count(),
            'remaining' => $outstanding->count(),
            'next_date' => $outstanding->first(fn (string $date) => $date >= $today),
            'held_dates' => $held,
        ];
    }
}
