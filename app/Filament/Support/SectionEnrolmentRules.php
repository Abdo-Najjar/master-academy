<?php

namespace App\Filament\Support;

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Services\RoomAvailabilityService;
use Closure;

/**
 * The three things that stop a student joining a section, in one place.
 *
 * They were written out again on every screen that enrols someone, which is how
 * they drifted: the schedule check ignored whether the clashing course had
 * already finished, so a student who took a course last term could never be put
 * in the same slot again. One copy, used everywhere.
 */
class SectionEnrolmentRules
{
    /** The section must have someone to teach it. */
    public static function hasTrainer(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            if (! $value) {
                return;
            }

            $section = Section::find($value);

            if ($section && ! $section->trainer_id) {
                $fail(__('Section :name has no trainer assigned. Assign a trainer to the section before registering students.', [
                    'name' => $section->name,
                ]));
            }
        };
    }

    /**
     * The section must still have a free seat. Students who withdrew gave
     * theirs back, so they must not keep it looking full.
     *
     * @param  int|null  $ignoreRegistrationId  the registration being edited
     */
    public static function hasRoom(?int $ignoreRegistrationId = null): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($ignoreRegistrationId): void {
            if (! $value) {
                return;
            }

            $section = Section::find($value);

            if (! $section || ! $section->capacity) {
                return;
            }

            $enrolled = Registration::query()
                ->where('section_id', $value)
                ->stillEnrolled()
                ->when($ignoreRegistrationId, fn ($q) => $q->where('id', '!=', $ignoreRegistrationId))
                ->count();

            if ($enrolled >= $section->capacity) {
                $fail(__('This section is full (capacity :capacity).', ['capacity' => $section->capacity]));
            }
        };
    }

    /**
     * The lessons must not land on top of another course the student is taking
     * at the same time. Courses whose term does not overlap this one — a
     * finished one above all — are not in the student's way.
     *
     * @param  int|null  $ignoreRegistrationId  the registration being edited
     */
    public static function noScheduleClash(?int $studentId, ?int $ignoreRegistrationId = null): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($studentId, $ignoreRegistrationId): void {
            if (! $studentId || ! $value) {
                return;
            }

            $otherSectionIds = Registration::query()
                ->where('student_id', $studentId)
                ->where('section_id', '!=', $value)
                ->when($ignoreRegistrationId, fn ($q) => $q->where('id', '!=', $ignoreRegistrationId))
                ->pluck('section_id');

            if ($otherSectionIds->isEmpty()) {
                return;
            }

            $newSection = Section::find($value);

            $newTimes = SectionTime::query()->where('section_id', $value)->get();

            $otherTimes = SectionTime::query()
                ->whereIn('section_id', $otherSectionIds)
                ->whereHas('section', fn ($q) => $q->runningBetween(
                    $newSection?->start_date,
                    $newSection?->end_date,
                ))
                ->with('section')
                ->get();

            foreach ($newTimes as $new) {
                foreach ($otherTimes as $other) {
                    if (strtolower((string) $new->day) !== strtolower((string) $other->day)) {
                        continue;
                    }

                    // Compared at HH:MM on both sides: the columns hold a mix of
                    // "12:00" and "12:00:00", and as text the shorter one sorts
                    // first — which made a course ending at noon look like it
                    // clashed with the one starting at noon.
                    if (RoomAvailabilityService::slotsOverlap(
                        $new->start_time, $new->end_time,
                        $other->start_time, $other->end_time,
                    )) {
                        $fail(__('Schedule conflict with the student\'s other section :name on :day at :time', [
                            'name' => $other->section?->name ?? '#'.$other->section_id,
                            'day' => __(ucfirst((string) $new->day)),
                            'time' => substr((string) $other->start_time, 0, 5).' - '.substr((string) $other->end_time, 0, 5),
                        ]));

                        return;
                    }
                }
            }
        };
    }
}
