<?php

namespace App\Models;

use App\Models\Concerns\AutoTranslatesMissing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class ExemptionType extends Model
{
    use AutoTranslatesMissing, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'discount_type', 'discount_value', 'is_active'];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Resolve the discount amount this exemption type grants for a given course fee.
     * Returns 0 when the type carries no preset discount (used as a label only).
     */
    public function computeDiscount(float $amountDue): float
    {
        if ($this->discount_value === null) {
            return 0.0;
        }

        $value = (float) $this->discount_value;

        return match ($this->discount_type) {
            'percentage' => round($amountDue * $value / 100, 2),
            'fixed' => min($value, max(0.0, $amountDue)),
            default => 0.0,
        };
    }
}
