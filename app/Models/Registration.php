<?php

namespace App\Models;

use App\Observers\RegistrationObserver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(RegistrationObserver::class)]
class Registration extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'section_id',
        'enrolled_at',
        'left_at',
        'leave_reason',
        'leave_source',
        'payment_type_id',
        'amount_due',
        'amount_paid',
        'exemption_amount',
        'exemption_type_id',
        'trainer_amount',
        'financial_status',
        'session_offset',
        'sessions_carried_over',
        'sessions_counted',
        'paid_through_session',
        'paused_at',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'left_at' => 'date',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'funded_amount' => 'decimal:2',
            'exemption_amount' => 'decimal:2',
            'trainer_amount' => 'decimal:2',
            'trainer_credited_amount' => 'decimal:2',
            'session_offset' => 'integer',
            'sessions_carried_over' => 'integer',
            'sessions_counted' => 'integer',
            'paid_through_session' => 'integer',
            'paused_at' => 'datetime',
        ];
    }

    /**
     * Rows that belong in a report: the registration itself is not trashed
     * (the SoftDeletes scope covers that) *and* neither is the student or the
     * section it points at. Soft-deleting a section or a student leaves their
     * registrations behind, so without this they keep showing up as revenue
     * and as outstanding dues long after the record was removed.
     */
    public function scopeReportable(Builder $query): Builder
    {
        return $query->whereHas('student')->whereHas('section');
    }

    /**
     * Registrations of a section that were already running on a given day.
     *
     * Attendance sheets and reports for a past date must show the roster as it
     * stood *then*, not as it stands now — otherwise a student enrolled last
     * week appears in (and is marked absent for) lessons held months earlier.
     * Rows with no enrolment date predate the column and are always included.
     */
    public function scopeEnrolledOn(Builder $query, int $sectionId, string|CarbonInterface $date): Builder
    {
        return $query->where('section_id', $sectionId)->enrolledBy($date);
    }

    /**
     * Registrations that were running on a given day: started by then and not
     * yet left. `left_at` is the first day the student is *no longer* in the
     * section, so a lesson on that date is already past them.
     */
    public function scopeEnrolledBy(Builder $query, string|CarbonInterface $date): Builder
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $query
            ->where(fn (Builder $q) => $q
                ->whereNull('enrolled_at')
                ->orWhereDate('enrolled_at', '<=', $day))
            ->where(fn (Builder $q) => $q
                ->whereNull('left_at')
                ->orWhereDate('left_at', '>', $day));
    }

    /** Registrations the student has not left. */
    public function scopeStillEnrolled(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    /** Has the student left this section? */
    public function hasLeft(): bool
    {
        return $this->left_at !== null;
    }

    /**
     * "Where did this money come from" line, used as the note on every wallet
     * movement tied to this registration. The old note was just
     * "Registration #12 — Name", which left the operator guessing which course,
     * which section and which branch the charge actually belonged to.
     */
    /** "Full Name (STU-123456)" — the student as they should read on a statement. */
    public function studentLabel(): string
    {
        $this->loadMissing('student');

        $name = $this->student?->getTranslation('name', app()->getLocale(), false);

        if (! $name) {
            return '#'.$this->student_id;
        }

        return $name.($this->student?->student_number ? ' ('.$this->student->student_number.')' : '');
    }

    /** "Section name — Branch", or just the section when it has no branch. */
    public function sectionLabel(): string
    {
        $this->loadMissing('section.branch');

        $section = $this->section?->name;

        if (! $section) {
            return '#'.$this->section_id;
        }

        return $section.($this->section?->branch?->name ? ' — '.$this->section->branch->name : '');
    }

    public function contextLabel(): string
    {
        $this->loadMissing(['student', 'section.subject', 'section.branch']);
        $locale = app()->getLocale();

        $parts = [
            __('Registration').' #'.$this->id,
            __('Student').': '.$this->studentLabel(),
        ];

        if ($course = $this->section?->subject?->getTranslation('name', $locale, false)) {
            $parts[] = __('Course').': '.$course;
        }

        if ($section = $this->section?->name) {
            $parts[] = __('Section').': '.$section;
        }

        if ($branch = $this->section?->branch?->name) {
            $parts[] = __('Branch').': '.$branch;
        }

        if ($trainer = $this->section?->trainer?->getTranslation('name', $locale, false)) {
            $parts[] = __('Trainer').': '.$trainer;
        }

        return implode(' · ', $parts);
    }

    /** Is this registration billed per number of sessions held? */
    public function isPerSessionBilled(): bool
    {
        return (bool) $this->section?->isPerSessionBilled();
    }

    /**
     * The day this student joined the section — everything before it belongs to
     * the section's history, not to their bill. Falls back to the student's own
     * enrolment date and then to the day the row was created, so registrations
     * from before the column existed still answer sensibly.
     */
    public function enrolmentDate(): CarbonInterface
    {
        if ($this->enrolled_at) {
            return $this->enrolled_at->copy()->startOfDay();
        }

        $this->loadMissing('student');

        return ($this->student?->enrolled_at ?? $this->created_at ?? now())->copy()->startOfDay();
    }

    /** Was this student in the section on the given day? */
    public function wasEnrolledOn(string|CarbonInterface $date): bool
    {
        $day = $date instanceof CarbonInterface ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        if ($this->left_at && $day->greaterThanOrEqualTo($this->left_at->copy()->startOfDay())) {
            return false;
        }

        return $day->greaterThanOrEqualTo($this->enrolmentDate());
    }

    /**
     * Sessions the student paid for but will never take, because they left
     * before using them up. The centre decides what to do about it — nothing is
     * moved automatically.
     */
    public function unusedPaidSessions(): int
    {
        if (! $this->hasLeft() || ! $this->isPerSessionBilled()) {
            return 0;
        }

        return max(0, $this->remainingSessions());
    }

    /** Sessions still covered by what the student paid; negative once owed. */
    public function remainingSessions(): int
    {
        return (int) $this->paid_through_session - (int) $this->sessions_counted;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['student_id', 'section_id', 'enrolled_at', 'left_at', 'leave_reason', 'payment_type_id', 'amount_due', 'amount_paid', 'exemption_amount', 'trainer_amount', 'note'])
            ->logOnlyDirty();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function exemptionType(): BelongsTo
    {
        return $this->belongsTo(ExemptionType::class);
    }

    /** Every stretch this registration spent out of the counting. */
    public function pauses(): HasMany
    {
        return $this->hasMany(RegistrationPause::class);
    }

    /**
     * Reverse wallet movements then soft-delete the registration.
     */
    public function deleteWithWalletAdjustments(): void
    {
        DB::transaction(function (): void {
            $this->loadMissing(['student', 'section.trainer']);
            $student = $this->student;
            $trainer = $this->section?->trainer;

            if ($student && (float) $this->amount_paid > 0) {
                $student->depositFloat((float) $this->amount_paid, [
                    'description' => __('Refund for :student — :name', [
                        'student' => $this->studentLabel(),
                        'name' => $this->sectionLabel(),
                    ]),
                    'note' => __('Cancelled').' — '.$this->contextLabel(),
                ]);
            }

            if ($trainer && (float) $this->trainer_credited_amount > 0) {
                $trainer->forceWithdrawFloat((float) $this->trainer_credited_amount, [
                    'description' => __('Refund for :student — :name', [
                        'student' => $this->studentLabel(),
                        'name' => $this->sectionLabel(),
                    ]),
                    'note' => __('Cancelled').' — '.$this->contextLabel(),
                ]);
            }

            $this->delete();
        });
    }
}
