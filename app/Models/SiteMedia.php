<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * One tile in the public "من قلب التدريب" gallery. The asset is either an
 * uploaded file or an external URL, so long videos can be hosted elsewhere
 * instead of filling local storage.
 */
class SiteMedia extends Model implements HasMedia
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, InteractsWithMedia, SoftDeletes;

    protected $table = 'site_media';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'type',
        'url',
        'is_active',
        'sort_order',
    ];

    /** @var list<string> */
    public array $translatable = ['title'];

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
        $this->addMediaCollection('file')->singleFile();
    }

    /** @return array<string, string> type value => translated label. */
    public static function typeOptions(): array
    {
        return [
            'image' => __('Image'),
            'video' => __('Video'),
        ];
    }

    public function getAssetUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('file') ?: ($this->url ?: null);
    }

    /** @param  Builder<SiteMedia>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
