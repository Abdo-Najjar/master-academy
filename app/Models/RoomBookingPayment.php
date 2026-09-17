<?php

namespace App\Models;

use App\Support\ReceiptAttachment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One instalment against a room booking, recorded exactly like a student
 * payment: an amount, how it was paid, when, and the receipt for it.
 */
class RoomBookingPayment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'room_booking_id',
        'payment_type_id',
        'amount',
        'paid_at',
        'note',
    ];

    public function registerMediaCollections(): void
    {
        // One receipt per instalment: re-uploading replaces it.
        $this->addMediaCollection(ReceiptAttachment::COLLECTION)->singleFile();
    }

    /** The attached receipt, or null when the instalment went in without one. */
    public function receiptUrl(): ?string
    {
        return ReceiptAttachment::url($this);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** Instalments banked inside a date range. */
    public function scopePaidBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }

    /** Rows that belong in a report: the booking they pay for still exists. */
    public function scopeReportable(Builder $query): Builder
    {
        return $query->whereHas('booking', fn (Builder $q) => $q->reportable());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['room_booking_id', 'payment_type_id', 'amount', 'paid_at', 'note'])
            ->logOnlyDirty();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
