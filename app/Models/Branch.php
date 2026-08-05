<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Branch extends Model
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'governorate_id', 'city_id', 'address', 'show_on_site', 'sort_order'];

    /** @var list<string> */
    public array $translatable = ['name', 'address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'show_on_site' => 'boolean',
        ];
    }

    /** @param  Builder<Branch>  $query */
    public function scopeOnSite(Builder $query): void
    {
        $query->where('show_on_site', true)->orderBy('sort_order')->orderBy('id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
