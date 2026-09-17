<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamGrade extends Model
{
    use BelongsToBranchThroughSection, HasFactory, LogsActivity;

    protected $fillable = ['exam_id', 'student_id', 'score', 'note'];

    /** A grade belongs to an exam, and the exam is what belongs to a section. */
    protected static function branchPathToSection(): array
    {
        return ['exams' => 'exam_id', 'sections' => 'section_id'];
    }

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['exam_id', 'student_id', 'score', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
