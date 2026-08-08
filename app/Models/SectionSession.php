<?php

namespace App\Models;

use App\Observers\SectionSessionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One lecture of a section on a concrete date.
 *
 * - regular: the normal lesson, advances the student's billing counter.
 * - makeup:  a paid replacement lesson, also advances the counter and earns
 *            the trainer their share.
 * - private: a stand-alone paid lesson with its own fee and trainer rate that
 *            never advances the regular counter.
 */
#[ObservedBy([SectionSessionObserver::class])]
class SectionSession extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_REGULAR = 'regular';

    public const TYPE_MAKEUP = 'makeup';

    public const TYPE_PRIVATE = 'private';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_HELD = 'held';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'section_id',
        'date',
        'start_time',
        'end_time',
        'type',
        'status',
        'cancellation_reason',
        'fee',
        'trainer_rate',
        'counts_toward_billing',
        'counted_at_billing',
        'note',
        'created_by',
    ];

    /**
     * Mirrors the column defaults so a freshly created instance behaves the
     * same in memory as it does after a reload — the billing observer reads
     * these attributes immediately, before any refetch.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::TYPE_REGULAR,
        'status' => self::STATUS_HELD,
        'counts_toward_billing' => true,
        'counted_at_billing' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fee' => 'decimal:2',
            'trainer_rate' => 'decimal:2',
            'counts_toward_billing' => 'boolean',
            'counted_at_billing' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['section_id', 'date', 'type', 'status', 'cancellation_reason', 'fee', 'trainer_rate', 'note'])
            ->logOnlyDirty();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_REGULAR => __('Regular Session'),
            self::TYPE_MAKEUP => __('Makeup Session'),
            self::TYPE_PRIVATE => __('Private Session'),
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_SCHEDULED => __('Scheduled'),
            self::STATUS_HELD => __('Held'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    /**
     * The regular session of a section on a given day, creating it as "held"
     * if it does not exist yet. Taking attendance for a day is what normally
     * calls this: the lesson evidently happened.
     *
     * A session that was explicitly cancelled is returned untouched — recording
     * attendance must not silently un-cancel a lesson.
     */
    public static function resolveForDay(int $sectionId, string $date): self
    {
        $session = static::query()
            ->where('section_id', $sectionId)
            ->where('type', self::TYPE_REGULAR)
            ->whereDate('date', $date)
            ->first();

        if (! $session) {
            return static::create([
                'section_id' => $sectionId,
                'date' => $date,
                'type' => self::TYPE_REGULAR,
                'status' => self::STATUS_HELD,
                'counts_toward_billing' => true,
            ]);
        }

        if ($session->status === self::STATUS_SCHEDULED) {
            $session->update(['status' => self::STATUS_HELD]);
        }

        return $session;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    /**
     * A held, non-private session is what moves a student one step closer to
     * their next payment.
     */
    public function advancesBillingCounter(): bool
    {
        return $this->status === self::STATUS_HELD
            && $this->counts_toward_billing
            && ! $this->isPrivate();
    }

    /** Trainer percentage for this session, falling back to the section's. */
    public function effectiveTrainerRate(): float
    {
        if ($this->trainer_rate !== null) {
            return (float) $this->trainer_rate;
        }

        return (float) ($this->section?->effectiveTrainerRate() ?? 0);
    }
}
