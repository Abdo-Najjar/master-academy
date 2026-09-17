<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Section extends Model implements HasMedia
{
    use BelongsToBranch, HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** One price for the whole course, paid up front. */
    public const FEE_TYPE_FIXED_COURSE = 'fixed_course';

    /** `cycle_fee` charged every `sessions_per_cycle` sessions held. */
    public const FEE_TYPE_PER_SESSIONS = 'per_sessions';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'subject_id',
        'branch_id',
        'trainer_id',
        'start_date',
        'end_date',
        'price',
        'fee_type',
        'sessions_per_cycle',
        'cycle_fee',
        'auto_charge_cycles',
        'trainer_rate',
        'capacity',
        'min_capacity',
        'training_hours',
        'section_type',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'price' => 'decimal:2',
            'cycle_fee' => 'decimal:2',
            'sessions_per_cycle' => 'integer',
            'auto_charge_cycles' => 'boolean',
            'trainer_rate' => 'decimal:4',
            'capacity' => 'integer',
            'min_capacity' => 'integer',
            'training_hours' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'subject_id', 'branch_id', 'trainer_id', 'start_date', 'end_date', 'price', 'fee_type', 'sessions_per_cycle', 'cycle_fee', 'auto_charge_cycles', 'trainer_rate', 'capacity', 'min_capacity', 'training_hours'])
            ->logOnlyDirty();
    }

    /** @return array<string, string> */
    public static function feeTypeOptions(): array
    {
        return [
            self::FEE_TYPE_FIXED_COURSE => __('Full course fee'),
            self::FEE_TYPE_PER_SESSIONS => __('Fee per number of sessions'),
        ];
    }

    public function isPerSessionBilled(): bool
    {
        return $this->fee_type === self::FEE_TYPE_PER_SESSIONS
            && (int) $this->sessions_per_cycle > 0;
    }

    /**
     * What this section actually costs, whichever way it is priced.
     *
     * A per-session section leaves `price` at zero — its money lives in
     * `cycle_fee` — so anything that reads `price` blindly reports it as free.
     * Every screen that shows a section's fee goes through here instead.
     */
    public function displayFee(): float
    {
        return $this->isPerSessionBilled()
            ? (float) $this->cycle_fee
            : (float) $this->price;
    }

    /** What that fee is called: a course price, or the fee for one cycle. */
    public function feeLabel(): string
    {
        return $this->isPerSessionBilled()
            ? __('Fee Per Cycle')
            : __('Course Fee');
    }

    /** `60 ₪ / 8 حصص` for per-session sections, plain money otherwise. */
    public function feeSummary(): string
    {
        $amount = number_format($this->displayFee(), 2).' ₪';

        if (! $this->isPerSessionBilled()) {
            return $amount;
        }

        return $amount.' / '.__(':count sessions', ['count' => (int) $this->sessions_per_cycle]);
    }

    /**
     * Whether finishing a cycle charges the student on its own, instead of
     * only flagging them as due and waiting for someone to collect by hand.
     */
    public function autoChargesCycles(): bool
    {
        return $this->isPerSessionBilled()
            && (bool) $this->auto_charge_cycles
            && (float) $this->cycle_fee > 0;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('materials');
    }

    /**
     * Sections that could genuinely collide with a course running from `$from`
     * to `$to` — the filter every trainer/room/student clash check runs first.
     *
     * A finished course holds nothing: once its end date is past, its trainer
     * and its room are free again at that hour, and the desk must be able to
     * put a new section there. Two courses that never run at the same time do
     * not collide either, however much their weekly slots look alike.
     *
     * A null date is open-ended: no end date means "still running", no start
     * date means "from whenever". A new section with no start date is taken as
     * starting today, which is when it is being created.
     */
    public function scopeRunningBetween(Builder $query, mixed $from = null, mixed $to = null): Builder
    {
        $today = now()->startOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : null;
        $end = $to ? Carbon::parse($to)->startOfDay() : null;

        // Anything already over is out, whatever the incoming range says.
        $earliest = $start && $start->greaterThan($today) ? $start : $today;

        return $query
            ->where(fn (Builder $q) => $q
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $earliest->toDateString()))
            ->when($end, fn (Builder $q) => $q
                ->where(fn (Builder $q2) => $q2
                    ->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $end->toDateString())));
    }

    /**
     * Seats currently taken. Students who withdrew gave their seat back, so
     * this is the roster as it stands rather than everyone who ever joined.
     */
    public function enrolledCount(): int
    {
        return $this->registrations()->stillEnrolled()->count();
    }

    /**
     * "12 / 30", or just "12" on a section with no ceiling — the line the desk
     * reads to know whether there is room left.
     */
    public function seatsSummary(): string
    {
        $enrolled = $this->enrolledCount();

        return $this->capacity
            ? $enrolled.' / '.$this->capacity
            : (string) $enrolled;
    }

    /** Is the section short of the number of students it needs to run? */
    public function isBelowMinimum(): bool
    {
        return $this->min_capacity !== null && $this->enrolledCount() < $this->min_capacity;
    }

    /** Are all the seats taken? */
    public function isFull(): bool
    {
        return $this->capacity !== null && $this->enrolledCount() >= $this->capacity;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function times(): HasMany
    {
        return $this->hasMany(SectionTime::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * The roster as it stood on a given day: students who had already joined by
     * then. Taking attendance for a past date must not list — let alone mark —
     * students who only enrolled afterwards.
     *
     * @return Collection<int, Registration>
     */
    public function rosterOn(string|CarbonInterface $date)
    {
        return $this->registrations()
            ->enrolledBy($date)
            ->with('student')
            ->get();
    }

    /**
     * Weekday names the section actually meets on, lower-cased the way
     * `section_times.day` stores them ('sunday', 'tuesday', …).
     *
     * @return list<string>
     */
    public function scheduledWeekdays(): array
    {
        return $this->times
            ->pluck('day')
            ->filter()
            ->map(fn (string $day): string => strtolower($day))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether a lesson happens on a date — the question the attendance sheet
     * asks before it lets a day be opened.
     *
     * Two things can put a lesson on a day: the weekly timetable, or a lesson
     * row someone entered by hand. The second half is what makes extra and
     * make-up lessons workable at all: an extra lesson is by definition off the
     * timetable, and without this the sheet would refuse the very day it was
     * added for — while the billing counter went on charging for it, since
     * `SessionBillingService` counts the lesson row and never consults the
     * timetable.
     */
    public function meetsOn(string|CarbonInterface $date): bool
    {
        return $this->matchesTimetableOn($date) || $this->hasLessonOn($date);
    }

    /**
     * Whether the weekly timetable puts a lesson on a date: the weekday is on
     * the timetable and the date falls inside the course window. A section with
     * no timetable rows has no restriction to enforce, so every day is allowed.
     */
    public function matchesTimetableOn(string|CarbonInterface $date): bool
    {
        $day = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);
        $day = $day->startOfDay();

        if ($this->start_date && $day->lt($this->start_date->copy()->startOfDay())) {
            return false;
        }

        if ($this->end_date && $day->gt($this->end_date->copy()->startOfDay())) {
            return false;
        }

        $weekdays = $this->scheduledWeekdays();

        if ($weekdays === []) {
            return true;
        }

        return in_array(strtolower($day->englishDayOfWeek), $weekdays, true);
    }

    /**
     * Whether a real lesson row sits on this date.
     *
     * Cancelled lessons do not count — nothing was taught. Private lessons do
     * not either: they are billed on their own and never advance the regular
     * counter, so opening the sheet on one would make
     * `SectionSession::resolveForDay()` raise a regular lesson beside it and
     * charge the whole roster for a lesson only one student sat.
     */
    public function hasLessonOn(string|CarbonInterface $date): bool
    {
        $day = ($date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date))->toDateString();

        return in_array($day, $this->lessonDates(), true);
    }

    /**
     * Dates this section has a real lesson row on, as `Y-m-d`.
     *
     * Held once per instance: the attendance day picker asks about forty-odd
     * days in a single render, and `meetsOn()` is the hot path in that loop.
     *
     * @return list<string>
     */
    public function lessonDates(): array
    {
        return $this->lessonDates ??= $this->sessions()
            ->where('status', '!=', SectionSession::STATUS_CANCELLED)
            ->where('type', '!=', SectionSession::TYPE_PRIVATE)
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Cached answer for `lessonDates()`. Not an attribute — an instance that
     * has just recorded a lesson calls `refresh()`, which leaves this stale, so
     * anything writing lessons re-reads the section.
     *
     * @var list<string>|null
     */
    protected ?array $lessonDates = null;

    /** How many lessons the centre counts as one "month" for this section. */
    public function sessionsPerMonth(int $fallback): int
    {
        $configured = (int) $this->sessions_per_cycle;

        return $configured > 0 ? $configured : $fallback;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(SectionSession::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (! $this->start_date || ! $this->end_date) {
                    return 'scheduled';
                }
                $today = now()->startOfDay();
                if ($this->start_date->isFuture()) {
                    return 'upcoming';
                }
                if ($this->end_date->isPast()) {
                    return 'completed';
                }
                if ($this->start_date->lte($today) && $this->end_date->gte($today)) {
                    return 'active';
                }

                return 'scheduled';
            }
        );
    }

    /**
     * Compute the effective trainer rate (percent), falling back to the trainer's default rate.
     */
    public function effectiveTrainerRate(): float
    {
        if ($this->trainer_rate !== null) {
            return (float) $this->trainer_rate;
        }

        return (float) ($this->trainer?->default_rate ?? 0);
    }
}
