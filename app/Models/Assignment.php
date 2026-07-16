<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Assignment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'section_id',
        'trainer_id',
        'title',
        'description',
        'due_date',
        'max_points',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'max_points' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Files the trainer attaches to the assignment brief.
        $this->addMediaCollection('attachments');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast();
    }
}
