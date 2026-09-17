<?php

namespace App\Observers;

use App\Models\RoomBooking;
use App\Models\RoomBookingTime;
use App\Services\RoomAvailabilityService;
use App\Support\RoomScheduleLock;
use Illuminate\Validation\ValidationException;

/**
 * Block saving a booking slot that would put two things in one room at once.
 *
 * The mirror of SectionTimeObserver: whichever of the two is entered second is
 * the one that is refused, so the hall can never be promised twice.
 */
class RoomBookingTimeObserver
{
    public function saving(RoomBookingTime $time): void
    {
        $time->loadMissing('booking');
        $booking = $time->booking;

        if (! $booking || ! $time->day || ! $time->start_time || ! $time->end_time) {
            return;
        }

        // A cancelled booking is not holding anything, so nothing can clash
        // with it and it cannot clash with anything else either.
        if ($booking->isCancelled() || ! $booking->room_id) {
            return;
        }

        // Held from here until the row is written, so a second desk booking the
        // same hall in the same second waits rather than reading a timetable
        // this one is halfway through changing.
        RoomScheduleLock::acquire((int) $booking->room_id, $time);

        try {
            $this->refuseClashes($time, $booking);
        } catch (\Throwable $e) {
            // Nothing is being written, so the room goes back immediately.
            RoomScheduleLock::release($time);

            throw $e;
        }
    }

    /** The room goes back once the row is safely in. */
    public function saved(RoomBookingTime $time): void
    {
        RoomScheduleLock::release($time);
    }

    private function refuseClashes(RoomBookingTime $time, RoomBooking $booking): void
    {

        // A booking cannot overlap itself: one hall cannot hold the same event
        // twice at once, and two slots that do would print as a duplicate on
        // every calendar. Checked here rather than only in the form because
        // `occupant()` looks past the whole booking to avoid colliding with the
        // row being edited, which makes it blind to the booking's own siblings.
        $sibling = RoomAvailabilityService::applyOverlap(
            $booking->times()->where('day', strtolower((string) $time->day)),
            'room_booking_times',
            (string) $time->start_time,
            (string) $time->end_time,
        )
            ->when($time->id, fn ($q) => $q->whereKeyNot($time->id))
            ->first();

        if ($sibling) {
            throw ValidationException::withMessages([
                'times' => __('This booking already has a slot on :day at :time', [
                    'day' => __(ucfirst(strtolower((string) $time->day))),
                    'time' => substr((string) $sibling->start_time, 0, 5).' - '.substr((string) $sibling->end_time, 0, 5),
                ]),
            ]);
        }

        $occupant = RoomAvailabilityService::occupant(
            roomId: (int) $booking->room_id,
            day: (string) $time->day,
            startTime: (string) $time->start_time,
            endTime: (string) $time->end_time,
            from: $booking->start_date,
            to: $booking->end_date,
            ignoreBookingId: (int) $booking->id,
        );

        if ($occupant) {
            throw ValidationException::withMessages([
                'times' => RoomAvailabilityService::message($occupant, (string) $time->day),
            ]);
        }
    }
}
