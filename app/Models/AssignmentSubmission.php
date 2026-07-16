<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssignmentSubmission extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /** Max upload size for a submitted file, in bytes (20 MB). */
    public const MAX_FILE_BYTES = 20 * 1024 * 1024;

    /** @var list<string> */
    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'grade',
        'feedback',
        'submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        // The single file the student uploads with a submission (max 20 MB).
        $this->addMediaCollection('attachment')->singleFile();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isGraded(): bool
    {
        return $this->grade !== null;
    }
}
