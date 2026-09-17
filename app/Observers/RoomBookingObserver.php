<?php

namespace App\Observers;

use App\Models\RoomBooking;
use App\Models\RoomBookingTime;
use App\Services\RoomAvailabilityService;
use Illuminate\Validation\ValidationException;

/**
 * Moving a booking is as capable of double-booking a room as adding a slot to
 * it: change the room, or stretch the dates over a term that already has
 * lessons in it, and every one of its slots lands somewhere new. The slot
 * observer never fires for that — the slot rows themselves did not change — so
 * the booking re-checks them on its own way in.
 */
class RoomBookingObserver
{
    public function saving(RoomBooking $booking): void
    {
        if (! $booking->exists || $booking->isCancelled() || ! $booking->room_id) {
            return;
        }

        // Only a move can invalidate slots that were fine a moment ago.
        if (! $booking->isDirty(['room_id', 'start_date', 'end_date', 'status'])) {
            return;
        }

        foreach ($booking->times()->get() as $time) {
            /** @var RoomBookingTime $time */
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
}
