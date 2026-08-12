<?php

namespace App\Models;

use App\Observers\RegistrationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'payment_type_id',
        'amount_due',
        'amount_paid',
        'exemption_amount',
        'exemption_type_id',
        'trainer_amount',
        'financial_status',
        'session_offset',
        'sessions_counted',
        'paid_through_session',
        'paused_at',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'funded_amount' => 'decimal:2',
            'exemption_amount' => 'decimal:2',
            'trainer_amount' => 'decimal:2',
            'trainer_credited_amount' => 'decimal:2',
            'session_offset' => 'integer',
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

    /** Sessions still covered by what the student paid; negative once owed. */
    public function remainingSessions(): int
    {
        return (int) $this->paid_through_session - (int) $this->sessions_counted;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['student_id', 'section_id', 'payment_type_id', 'amount_due', 'amount_paid', 'exemption_amount', 'trainer_amount', 'note'])
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
