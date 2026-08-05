<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * A training program as advertised on the public site. Kept separate from
 * Subject so marketing copy (price shown to visitors, duration wording, cover
 * image) can change without touching the sections/registrations data, while
 * still allowing an optional link to the real subject.
 */
class Program extends Model implements HasMedia
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'category',
        'icon',
        'duration',
        'price',
        'branches_label',
        'registration_url',
        'subject_id',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    /** @var list<string> */
    public array $translatable = ['title', 'description', 'duration', 'price', 'branches_label'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function joinApplications(): HasMany
    {
        return $this->hasMany(JoinApplication::class);
    }

    /** @return array<string, string> category value => translated label. */
    public static function categoryOptions(): array
    {
        return [
            'creative' => __('Creative'),
            'technical' => __('Technical'),
            'professional' => __('Professional'),
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        return static::categoryOptions()[$this->category] ?? __('Training Program');
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    /** @param  Builder<Program>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
