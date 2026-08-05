<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Testimonial extends Model implements HasMedia
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'role',
        'quote',
        'student_id',
        'is_active',
        'sort_order',
    ];

    /** @var list<string> */
    public array $translatable = ['name', 'role', 'quote'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /** Optional link back to the graduate this testimonial came from. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Falls back to the linked student's profile photo when no avatar is uploaded. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar')
            ?: ($this->student?->getFirstMediaUrl('main') ?: null);
    }

    /** @param  Builder<Testimonial>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
