<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'all_sections',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'all_sections' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class);
    }

    public function dismissals(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'announcement_dismissals')
            ->withPivot('dismissed_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        $now = now();

        if ($this->published_at && $this->published_at->gt($now)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Scope: currently visible (published, not expired).
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Scope: announcements visible to a given student (matches their sections
     * or is flagged as all_sections).
     */
    public function scopeForStudent($query, Student $student)
    {
        $sectionIds = $student->registrations()->pluck('section_id');

        return $query->where(function ($q) use ($sectionIds) {
            $q->where('all_sections', true)
                ->orWhereHas('sections', fn ($s) => $s->whereIn('sections.id', $sectionIds));
        });
    }
}
