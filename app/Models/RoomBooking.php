<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Observers\RoomBookingObserver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A room let out to someone who is not a section — a workshop, an exam sitting,
 * an outside body renting the hall.
 *
 * It is shaped like a section on purpose: a date range plus the weekly slots
 * inside it. That makes a one-off booking a range of a single day, lets the
 * general calendar draw it next to the lessons without a second code path, and
 * lets one room-conflict rule cover both.
 *
 * The money side is the mirror of a registration: the booking carries the
 * agreed price, and what was actually handed over arrives as instalments, each
 * with its own method and receipt.
 */
#[ObservedBy([RoomBookingObserver::class])]
class RoomBooking extends Model
{
    use BelongsToBranch, HasFactory, LogsActivity, SoftDeletes;

    public const STATUS_TENTATIVE = 'tentative';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'room_id',
        'branch_id',
        'title',
        'client_name',
        'client_phone',
        'start_date',
        'end_date',
        'price',
        'status',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_TENTATIVE => __('Tentative'),
            self::STATUS_CONFIRMED => __('Confirmed'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }

    /**
     * A cancelled booking holds nothing: it is off the calendar, it frees the
     * room for anyone else, and it is not income.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** Bookings that still hold their room. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    /** Bookings whose date range overlaps the given one — what a calendar draws. */
    public function scopeOverlapping(Builder $query, mixed $from = null, mixed $to = null): Builder
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : null;
        $end = $to ? Carbon::parse($to)->startOfDay() : null;

        return $query
            ->when($start, fn (Builder $q) => $q->whereDate('end_date', '>=', $start->toDateString()))
            ->when($end, fn (Builder $q) => $q->whereDate('start_date', '<=', $end->toDateString()));
    }

    /**
     * Bookings that still hold their room across the given range — the same rule
     * `Section::runningBetween()` applies to courses: a booking that is already
     * over frees the hall whatever range is asked about, and a cancelled one
     * never held it in the first place.
     */
    public function scopeHoldingBetween(Builder $query, mixed $from = null, mixed $to = null): Builder
    {
        $today = now()->startOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : null;
        $end = $to ? Carbon::parse($to)->startOfDay() : null;

        $earliest = $start && $start->greaterThan($today) ? $start : $today;

        return $query
            ->active()
            ->whereDate('end_date', '>=', $earliest->toDateString())
            ->when($end, fn (Builder $q) => $q->whereDate('start_date', '<=', $end->toDateString()));
    }

    /** Rows that belong in a report: the booking still points at a live room. */
    public function scopeReportable(Builder $query): Builder
    {
        return $query->whereHas('room');
    }

    /**
     * SQL for "how much has been banked against this booking" — a correlated
     * sum rather than a stored column, so deleting an instalment cannot leave a
     * total behind that no longer adds up.
     */
    public static function paidExpression(): string
    {
        return '(SELECT COALESCE(SUM(rbp.amount), 0) FROM room_booking_payments rbp'
            .' WHERE rbp.room_booking_id = room_bookings.id AND rbp.deleted_at IS NULL)';
    }

    /** Filter on `unpaid` / `partial` / `paid`, computed from the instalments. */
    public function scopeWherePaymentStatus(Builder $query, string $status): Builder
    {
        $paid = self::paidExpression();

        return match ($status) {
            'unpaid' => $query->whereRaw($paid.' <= 0.009'),
            'paid' => $query->whereRaw('room_bookings.price - '.$paid.' <= 0.009'),
            'partial' => $query
                ->whereRaw($paid.' > 0.009')
                ->whereRaw('room_bookings.price - '.$paid.' > 0.009'),
            default => $query,
        };
    }

    /** @return array<string, string> */
    public static function paymentStatusOptions(): array
    {
        return [
            'unpaid' => __('Unpaid'),
            'partial' => __('Partially Paid'),
            'paid' => __('Paid'),
        ];
    }

    /** "Saturday 10:00 - 12:00 · Sunday 10:00 - 12:00" — the slots in one line. */
    public function timesLabel(): string
    {
        $this->loadMissing('times');

        return $this->times
            ->map(fn (RoomBookingTime $time): string => __(ucfirst(strtolower((string) $time->day)))
                .' '.substr((string) $time->start_time, 0, 5)
                .' - '.substr((string) $time->end_time, 0, 5))
            ->implode(' · ');
    }

    /** Does this booking run on the given day? */
    public function runsOn(string|CarbonInterface $date): bool
    {
        $day = $date instanceof CarbonInterface ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        if ($this->isCancelled()) {
            return false;
        }

        return $day->betweenIncluded(
            $this->start_date->copy()->startOfDay(),
            $this->end_date->copy()->startOfDay(),
        );
    }

    /**
     * What has actually been handed over so far.
     *
     * Reads the aggregate the query already loaded when there is one — the
     * bookings table asks this of every row, and re-counting the instalments
     * per row would turn one screen into one query per booking.
     */
    public function paidAmount(): float
    {
        if (array_key_exists('paid_total', $this->attributes)) {
            return round((float) $this->attributes['paid_total'], 2);
        }

        return round((float) $this->payments()->sum('amount'), 2);
    }

    /** What is still owed on the agreed price; never negative. */
    public function remainingAmount(): float
    {
        return max(0.0, round((float) $this->price - $this->paidAmount(), 2));
    }

    /** `unpaid` / `partial` / `paid` — driven by the instalments, never stored. */
    public function paymentStatus(): string
    {
        $paid = $this->paidAmount();

        if ($paid <= 0.009) {
            return 'unpaid';
        }

        return $this->remainingAmount() <= 0.009 ? 'paid' : 'partial';
    }

    /** "Workshop — Room 3", the line a statement or a receipt should read. */
    public function contextLabel(): string
    {
        $this->loadMissing('room');

        return $this->title.($this->room?->number ? ' — '.__('Room').' '.$this->room->number : '');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['room_id', 'branch_id', 'title', 'client_name', 'client_phone', 'start_date', 'end_date', 'price', 'status', 'note'])
            ->logOnlyDirty();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function times(): HasMany
    {
        return $this->hasMany(RoomBookingTime::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RoomBookingPayment::class);
    }
}
