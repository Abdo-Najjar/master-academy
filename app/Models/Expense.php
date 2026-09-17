<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
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
 * Money the centre paid out — rent, bills, salaries, anything that is not a
 * student refund. Deliberately not a wallet movement: a wallet belongs to a
 * student or a trainer, and rent belongs to neither. The shape mirrors what the
 * desk already fills in when it takes money from a student — an amount, how it
 * was paid, when, and the receipt for it — so the same habits carry over.
 */
class Expense extends Model implements HasMedia
{
    use BelongsToBranch, HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'expense_type_id',
        'branch_id',
        'payment_type_id',
        'amount',
        'spent_at',
        'payee',
        'reference',
        'note',
    ];

    public function registerMediaCollections(): void
    {
        // One receipt per expense: re-uploading replaces it rather than
        // leaving the desk to guess which of two is the real voucher.
        $this->addMediaCollection(ReceiptAttachment::COLLECTION)->singleFile();
    }

    /** The attached receipt, or null when the expense went in without one. */
    public function receiptUrl(): ?string
    {
        return ReceiptAttachment::url($this);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'date',
        ];
    }

    /**
     * Rows that belong in a report: the expense still points at a live type.
     * Soft-deleting a type leaves its expenses behind, and they would otherwise
     * keep counting against the period they were spent in with no label on them.
     */
    public function scopeReportable(Builder $query): Builder
    {
        return $query->whereHas('expenseType');
    }

    /** Expenses paid inside a date range, by the day the money went out. */
    public function scopeSpentBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['expense_type_id', 'branch_id', 'payment_type_id', 'amount', 'spent_at', 'payee', 'reference', 'note'])
            ->logOnlyDirty();
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
