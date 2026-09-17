<?php

namespace App\Models;

use App\Observers\RoomBookingTimeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One weekly slot of a booking — the booking's own `SectionTime`.
 *
 * The room is on the booking rather than on the slot: a booking rents one room
 * for its whole run, unlike a section that can move between rooms by weekday.
 */
#[ObservedBy([RoomBookingTimeObserver::class])]
class RoomBookingTime extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['room_booking_id', 'day', 'start_time', 'end_time'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }
}
