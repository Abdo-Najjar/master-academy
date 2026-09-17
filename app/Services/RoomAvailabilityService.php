<?php

namespace App\Services;

use App\Models\RoomBooking;
use App\Models\RoomBookingTime;
use App\Models\Section;
use App\Models\SectionTime;
use App\Support\BranchContext;
use Carbon\Carbon;

/**
 * "Is this room free then?" — asked from both sides of the same wall.
 *
 * A room used to be held by sections alone, so the section-time observer could
 * answer it by looking at its own table. Now an outside booking holds the hall
 * just as firmly as a lesson does, and either one may be entered first, so both
 * have to ask the same question of both tables. Keeping the answer here means a
 * lesson can never be slotted into a booked hall, nor a hall booked over a
 * lesson, whichever screen the row was typed on.
 */
class RoomAvailabilityService
{
    /**
     * What already holds `$roomId` in that slot, or null when it is free.
     *
     * `$ignoreSectionId` / `$ignoreBookingId` exclude the record being edited —
     * without them every save would collide with the row it is replacing.
     *
     * `visible` says whether the thing standing in the room is something this
     * employee is allowed to know about. It rarely is not — rooms belong to a
     * branch — but mixed data happens, and when it does the clash still has to
     * be refused without naming another site's course on the screen.
     *
     * @return array{label: string, time: string, is_booking: bool, visible: bool}|null
     */
    public static function occupant(
        int $roomId,
        string $day,
        string $startTime,
        string $endTime,
        mixed $from = null,
        mixed $to = null,
        ?int $ignoreSectionId = null,
        ?int $ignoreBookingId = null,
    ): ?array {
        $day = strtolower($day);
        $start = self::normalise($startTime);
        $end = self::normalise($endTime);

        if ($start === null || $end === null || $start >= $end) {
            return null;
        }

        // Deliberately blind to branches. A room is a physical space: if
        // anything at all is standing in it at that hour the answer is "taken",
        // and an employee who cannot see the other branch's course must still
        // be stopped from booking over it rather than told the hall is free.
        $lesson = self::applyOverlap(
            SectionTime::query()->where('day', $day)->where('room_id', $roomId),
            'section_times',
            $start,
            $end,
        )
            ->when($ignoreSectionId, fn ($q) => $q->where('section_id', '!=', $ignoreSectionId))
            ->whereHas('section', fn ($q) => $q->withoutGlobalScope('branch')->runningBetween($from, $to))
            ->first();

        if ($lesson) {
            // Loaded past the branch scope on purpose: without it the eager
            // load comes back empty for another site's course and the message
            // reads "#3", which tells the operator nothing at all.
            $section = Section::query()->withoutGlobalScope('branch')->find($lesson->section_id);

            return self::describe(
                label: $section?->name ?? '#'.$lesson->section_id,
                branchId: $section?->branch_id,
                time: self::slotLabel($lesson->start_time, $lesson->end_time),
                isBooking: false,
            );
        }

        $booking = self::applyOverlap(
            RoomBookingTime::query()->where('day', $day),
            'room_booking_times',
            $start,
            $end,
        )
            ->when($ignoreBookingId, fn ($q) => $q->where('room_booking_id', '!=', $ignoreBookingId))
            ->whereHas('booking', fn ($q) => $q
                ->withoutGlobalScope('branch')
                ->where('room_id', $roomId)
                ->holdingBetween($from, $to))
            ->first();

        if ($booking) {
            $held = RoomBooking::query()->withoutGlobalScope('branch')->find($booking->room_booking_id);

            return self::describe(
                label: $held?->title ?? '#'.$booking->room_booking_id,
                branchId: $held?->branch_id,
                time: self::slotLabel($booking->start_time, $booking->end_time),
                isBooking: true,
            );
        }

        return null;
    }

    /**
     * @return array{label: string, time: string, is_booking: bool, visible: bool}
     */
    protected static function describe(string $label, mixed $branchId, string $time, bool $isBooking): array
    {
        $viewer = BranchContext::currentBranchId();

        return [
            'label' => $label,
            'time' => $time,
            'is_booking' => $isBooking,
            'visible' => $viewer === null || (int) $branchId === $viewer,
        ];
    }

    /** The sentence to put in front of the operator about a clash. */
    public static function message(array $occupant, string $day): string
    {
        $replacements = [
            'name' => $occupant['label'],
            'day' => __(ucfirst(strtolower($day))),
            'time' => $occupant['time'],
        ];

        // Whatever is standing there belongs to a site this employee cannot
        // see. They still may not book over it, and they still may not be told
        // whose it is — so they are told the truth without the name.
        if (! ($occupant['visible'] ?? true)) {
            return __('The room is taken on :day at :time by another branch.', $replacements);
        }

        return $occupant['is_booking']
            ? __('Room is booked for :name on :day at :time', $replacements)
            : __('Room is already used by :name on :day at :time', $replacements);
    }

    /**
     * Every weekday a booking's date range actually contains, so a slot cannot
     * be set for a day the booking never runs on — "Tuesday 10–12" on a booking
     * that starts and ends on a Saturday holds a room for nothing.
     *
     * A range of seven days or more contains every weekday, so it constrains
     * nothing and the caller is told as much with an empty answer.
     *
     * @return list<string> lowercase weekday names, or [] when unconstrained
     */
    public static function weekdaysInRange(mixed $from, mixed $to): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start) || $start->diffInDays($end) >= 6) {
            return [];
        }

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days[] = strtolower($cursor->format('l'));
            $cursor->addDay();
        }

        return array_values(array_unique($days));
    }

    /**
     * Narrow a query of weekly slots to the ones that genuinely overlap
     * `[$startTime, $endTime)`.
     *
     * The comparison is cut to `HH:MM` on both sides because the columns are
     * plain strings and the app is not consistent about seconds: a slot typed
     * through a picker arrives as "12:00:00" while one written by a seeder or
     * an import sits as "12:00". Compared as text, `'12:00' < '12:00:00'` is
     * true — the shorter string sorts first — so a lesson ending at noon was
     * reported as clashing with the one starting at noon, and back-to-back
     * slots in the same room were refused for no reason.
     *
     * `$table` is a table name from this file, never anything a user typed.
     */
    public static function applyOverlap(mixed $query, string $table, string $startTime, string $endTime): mixed
    {
        return $query
            ->whereRaw("substr({$table}.start_time, 1, 5) < ?", [self::minutes($endTime)])
            ->whereRaw("substr({$table}.end_time, 1, 5) > ?", [self::minutes($startTime)]);
    }

    /** `HH:MM` — the precision every picker in the app actually works in. */
    public static function minutes(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    /** Do two slots on the same day overlap? Both sides cut to `HH:MM`. */
    public static function slotsOverlap(mixed $startA, mixed $endA, mixed $startB, mixed $endB): bool
    {
        return self::minutes($startA) < self::minutes($endB)
            && self::minutes($endA) > self::minutes($startB);
    }

    /** "09:00 - 10:30" for a message, whatever precision the column holds. */
    protected static function slotLabel(mixed $start, mixed $end): string
    {
        return substr((string) $start, 0, 5).' - '.substr((string) $end, 0, 5);
    }

    /**
     * `H:i:s`, so a slot typed as "09:00" compares against one stored as
     * "09:00:00" — the columns are plain strings and the pickers are not
     * consistent about seconds.
     */
    protected static function normalise(mixed $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        try {
            return Carbon::parse((string) $time)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
