<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class PaymentType extends Model
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name'];

    /** @var list<string> */
    public array $translatable = ['name'];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
