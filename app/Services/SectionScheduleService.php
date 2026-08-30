<?php

namespace App\Services;

use App\Models\Section;
use App\Models\SectionSession;
use App\Models\SectionTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    /**
     * Lessons that exist as rows but not on any timetable — the extra and
     * make-up lessons someone entered by hand — for a set of sections inside a
     * date range, keyed by `Y-m-d`.
     *
     * Every calendar in the app draws `SectionTime` rows, and a lesson entered
     * for one date has no timetable row to be drawn from, so it was simply
     * invisible: added under "Class Sessions", counted by billing, and absent
     * from the month grid the desk actually reads. These come back shaped as
     * `SectionTime` instances — unsaved, carrying the lesson's own times and its
     * section — so the grids render them unchanged and only need to know how to
     * badge them.
     *
     * @param  list<int>  $sectionIds
     * @return array<string, Collection<int, SectionTime>>
     */
    public static function extraLessonsBetween(array $sectionIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($sectionIds === []) {
            return [];
        }

        $sessions = SectionSession::query()
            ->whereIn('section_id', $sectionIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            // A cancelled lesson is not on the calendar for the same reason it
            // is not on the bill: it did not happen.
            ->where('status', '!=', SectionSession::STATUS_CANCELLED)
            ->with(['section.subject', 'section.branch', 'section.trainer', 'section.times.room'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $lessons = [];

        foreach ($sessions as $session) {
            $section = $session->section;

            if (! $section) {
                continue;
            }

            $date = CarbonImmutable::parse($session->date);

            // A regular lesson on a timetable day is already on the grid; only
            // what the timetable cannot produce is added here. A private lesson
            // is always its own event, timetable day or not.
            //
            // "Has times at all" is asked first on purpose: a section with an
            // empty timetable meets on any day as far as the attendance sheet
            // is concerned, but draws nothing on a calendar — so every one of
            // its lessons has to come from here or it has no calendar at all.
            $onTimetable = $section->times->isNotEmpty() && $section->matchesTimetableOn($date);

            if (! $session->isPrivate() && $onTimetable) {
                continue;
            }

            $lessons[$date->toDateString()][] = self::asTimetableRow($session, $section, $date);
        }

        return array_map(fn (array $rows): Collection => collect($rows), $lessons);
    }

    /**
     * One lesson row dressed as the timetable row a calendar knows how to draw.
     *
     * Times are optional on a hand-entered lesson, so they fall back to the
     * section's usual slot for that weekday and then to a blank — a lesson with
     * no time is still a lesson worth seeing on the day.
     */
    protected static function asTimetableRow(SectionSession $session, Section $section, CarbonImmutable $date): SectionTime
    {
        $usual = $section->times
            ->first(fn (SectionTime $time): bool => strtolower((string) $time->day) === strtolower($date->format('l')));

        $row = new SectionTime([
            'section_id' => $section->getKey(),
            'day' => strtolower($date->format('l')),
            'start_time' => $session->start_time ?: $usual?->start_time,
            'end_time' => $session->end_time ?: $usual?->end_time,
            'room_id' => $usual?->room_id,
        ]);

        $row->setRelation('section', $section);
        $row->setRelation('room', $usual?->room);

        // What the grids badge it with. Set as plain attributes so a blade can
        // ask `$lesson->extra_session_type` without caring where it came from.
        $row->extra_session_id = $session->getKey();
        $row->extra_session_type = $session->type;
        $row->extra_session_status = $session->status;

        return $row;
    }
}
