<?php

namespace App\Observers;

use App\Models\SectionTime;
use Illuminate\Validation\ValidationException;

class SectionTimeObserver
{
    /**
     * Block saving a section time that would put a trainer in two places at once,
     * or two sections in the same room at the same time.
     */
    public function saving(SectionTime $time): void
    {
        $time->loadMissing('section.trainer');
        $section = $time->section;
        if (! $section || ! $time->day || ! $time->start_time || ! $time->end_time) {
            return;
        }

        $base = SectionTime::query()
            ->where('day', $time->day)
            ->where('section_id', '!=', $section->id)
            ->where('start_time', '<', $time->end_time)
            ->where('end_time', '>', $time->start_time)
            ->when($time->id, fn ($q) => $q->where('id', '!=', $time->id));

        // Trainer double-booking
        if ($section->trainer_id) {
            $conflict = (clone $base)
                ->whereHas('section', fn ($q) => $q->where('trainer_id', $section->trainer_id))
                ->with('section.trainer')
                ->first();
            if ($conflict) {
                $name = $conflict->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$conflict->section_id;
                throw ValidationException::withMessages([
                    'times' => __('Trainer is already teaching :name on :day at :time', [
                        'name' => $name,
                        'day' => __(ucfirst((string) $time->day)),
                        'time' => substr((string) $conflict->start_time, 0, 5).' - '.substr((string) $conflict->end_time, 0, 5),
                    ]),
                ]);
            }
        }

        // Room double-booking
        if ($time->room_id) {
            $conflict = (clone $base)
                ->where('room_id', $time->room_id)
                ->with('section')
                ->first();
            if ($conflict) {
                $name = $conflict->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$conflict->section_id;
                throw ValidationException::withMessages([
                    'times' => __('Room is already used by :name on :day at :time', [
                        'name' => $name,
                        'day' => __(ucfirst((string) $time->day)),
                        'time' => substr((string) $conflict->start_time, 0, 5).' - '.substr((string) $conflict->end_time, 0, 5),
                    ]),
                ]);
            }
        }
    }
}
