<?php

namespace App\Models;

use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([AttendanceObserver::class])]
class Attendance extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['section_id', 'student_id', 'date', 'status', 'is_makeup', 'makeup_for_date', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_makeup' => 'boolean',
            'makeup_for_date' => 'date',
        ];
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
     * @param  array<int, string>  $statuses  student_id => status
     * @param  array<int, string|null>  $notes  student_id => note
     */
    public static function recordDay(int $sectionId, string $date, array $statuses, array $notes = []): void
    {
        $existing = static::query()
            ->where('section_id', $sectionId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        foreach ($statuses as $studentId => $status) {
            $attributes = [
                'status' => $status,
                'note' => $notes[$studentId] ?? null,
            ];

            if ($row = $existing->get($studentId)) {
                $row->update($attributes);

                continue;
            }

            static::create($attributes + [
                'section_id' => $sectionId,
                'student_id' => $studentId,
                'date' => $date,
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
}
