<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
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
    protected $fillable = ['name', 'governorate_id', 'city_id', 'sort_order'];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
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
