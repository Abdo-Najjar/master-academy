<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Subject extends Model
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'color', 'sort_order'];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'subject_trainer')->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
