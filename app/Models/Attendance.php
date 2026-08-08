<?php

namespace App\Models;

use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy([AttendanceObserver::class])]
class Attendance extends Model
{
    use HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'section_id',
        'section_session_id',
        'student_id',
        'date',
        'status',
        'is_makeup',
        'makeup_for_date',
        'note',
        'recorded_by_type',
        'recorded_by_id',
        'updated_by_type',
        'updated_by_id',
        'recorded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_makeup' => 'boolean',
            'makeup_for_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['section_id', 'section_session_id', 'student_id', 'date', 'status', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Upsert a whole day's attendance for a section.
     *
     * The `date` column is written through a date cast, so it holds a full
     * `Y-m-d H:i:s` value. Matching it against the plain `Y-m-d` string the UI
     * works with never finds the existing row, which made `updateOrCreate()`
     * insert a duplicate and trip the (section, student, date) unique index —
     * so re-saving a day that was already recorded blew up. Resolving the rows
     * up front with `whereDate()` fixes that and drops the per-student SELECT.
     *
     * Taking attendance also means the lesson happened, so the matching
     * `SectionSession` is created (or promoted from scheduled to held) here —
     * that is what advances the per-session billing counters.
     *
     * @param  array<int, string>  $statuses  student_id => status
     * @param  array<int, string|null>  $notes  student_id => note
     * @param  \Illuminate\Database\Eloquent\Model|null  $actor  who is taking/editing the attendance
     */
    public static function recordDay(
        int $sectionId,
        string $date,
        array $statuses,
        array $notes = [],
        $actor = null,
        ?SectionSession $session = null,
    ): void {
        $session ??= SectionSession::resolveForDay($sectionId, $date);

        $existing = static::query()
            ->where('section_id', $sectionId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        foreach ($statuses as $studentId => $status) {
            $attributes = [
                'status' => $status,
                'note' => $notes[$studentId] ?? null,
                'section_session_id' => $session?->id,
            ];

            if ($row = $existing->get($studentId)) {
                $row->fill($attributes + [
                    'updated_by_type' => $actor ? $actor::class : null,
                    'updated_by_id' => $actor?->getKey(),
                ]);

                // Skip untouched rows so re-saving a day does not fill the
                // audit log with empty "changed nothing" entries.
                if ($row->isDirty(['status', 'note', 'section_session_id'])) {
                    $row->save();
                }

                continue;
            }

            static::create($attributes + [
                'section_id' => $sectionId,
                'student_id' => $studentId,
                'date' => $date,
                'recorded_by_type' => $actor ? $actor::class : null,
                'recorded_by_id' => $actor?->getKey(),
                'recorded_at' => now(),
            ]);
        }
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SectionSession::class, 'section_session_id');
    }

    public function recordedBy(): MorphTo
    {
        return $this->morphTo('recorded_by');
    }

    public function updatedBy(): MorphTo
    {
        return $this->morphTo('updated_by');
    }

    /** Human-readable name of whoever last touched this row. */
    public function actorName(): ?string
    {
        $actor = $this->updatedBy ?? $this->recordedBy;

        if (! $actor) {
            return null;
        }

        $name = $actor->name ?? null;

        return is_array($name) ? ($name[app()->getLocale()] ?? reset($name)) : $name;
    }
}
