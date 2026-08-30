<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One break in a registration's session counting: from `paused_from` until
 * `resumed_at` (or still running, when that is null). Lessons held inside the
 * window are not charged to the student.
 */
class RegistrationPause extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'registration_id',
        'paused_from',
        'resumed_at',
        'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paused_from' => 'date',
            'resumed_at' => 'date',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resumed_at');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
