<?php

namespace App\Observers;

use App\Models\Section;
use App\Models\SectionTime;
use App\Services\RoomAvailabilityService;
use App\Support\RoomScheduleLock;
use Illuminate\Validation\ValidationException;

class SectionTimeObserver
{
    /**
     * Block saving a section time that would put a trainer in two places at once,
     * or two sections in the same room at the same time.
     *
     * Only courses that are actually still running can hold a slot: once a
     * course is over, its trainer and its room are free again at that hour.
     */
    public function saving(SectionTime $time): void
    {
        $time->loadMissing('section.trainer');
        $section = $time->section;
        if (! $section || ! $time->day || ! $time->start_time || ! $time->end_time) {
            return;
        }

        // Held from here until the row is written: the check reads the
        // timetable and the insert happens after, and two desks saving into the
        // same hall in the same second would otherwise both read it free.
        RoomScheduleLock::acquire($time->room_id ? (int) $time->room_id : null, $time);

        try {
            $this->refuseClashes($time, $section);
        } catch (\Throwable $e) {
            RoomScheduleLock::release($time);

            throw $e;
        }
    }

    /** The room goes back once the row is safely in. */
    public function saved(SectionTime $time): void
    {
        RoomScheduleLock::release($time);
    }

    private function refuseClashes(SectionTime $time, Section $section): void
    {

        // Cut to HH:MM on both sides: the columns hold a mix of "12:00" and
        // "12:00:00", and comparing those as text makes back-to-back lessons
        // look like a clash.
        $base = RoomAvailabilityService::applyOverlap(
            SectionTime::query()
                ->where('day', $time->day)
                ->where('section_id', '!=', $section->id),
            'section_times',
            (string) $time->start_time,
            (string) $time->end_time,
        )
            ->when($time->id, fn ($q) => $q->where('id', '!=', $time->id))
            ->whereHas('section', fn ($q) => $q->runningBetween($section->start_date, $section->end_date));

        // Trainer double-booking
        if ($section->trainer_id) {
            $conflict = (clone $base)
                ->whereHas('section', fn ($q) => $q->where('trainer_id', $section->trainer_id))
                ->with('section.trainer')
                ->first();
            if ($conflict) {
                $name = $conflict->section?->name ?? '#'.$conflict->section_id;
                throw ValidationException::withMessages([
                    'times' => __('Trainer is already teaching :name on :day at :time', [
                        'name' => $name,
                        'day' => __(ucfirst((string) $time->day)),
                        'time' => substr((string) $conflict->start_time, 0, 5).' - '.substr((string) $conflict->end_time, 0, 5),
                    ]),
                ]);
            }
        }

        // Room double-booking — against other lessons *and* against halls let
        // out to someone else, which hold the room just as firmly.
        if ($time->room_id) {
            $occupant = RoomAvailabilityService::occupant(
                roomId: (int) $time->room_id,
                day: (string) $time->day,
                startTime: (string) $time->start_time,
                endTime: (string) $time->end_time,
                from: $section->start_date,
                to: $section->end_date,
                ignoreSectionId: (int) $section->id,
            );

            if ($occupant) {
                throw ValidationException::withMessages([
                    'times' => RoomAvailabilityService::message($occupant, (string) $time->day),
                ]);
            }
        }
    }
}
