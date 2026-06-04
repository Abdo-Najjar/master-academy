<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'complainable_type',
        'complainable_id',
        'subject',
        'body',
        'status',
        'admin_reply',
        'handled_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function complainable(): MorphTo
    {
        return $this->morphTo();
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN => __('Open'),
            self::STATUS_IN_PROGRESS => __('In Progress'),
            self::STATUS_RESOLVED => __('Resolved'),
        ];
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::statuses()[$this->status] ?? $this->status);
    }

    protected function statusColor(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->status) {
            self::STATUS_OPEN => 'warning',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_RESOLVED => 'success',
            default => 'gray',
        });
    }

    /**
     * Complaints older than one month are archived: hidden from the admin panel
     * and from the student/trainer portals (the rows are kept, just not shown).
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subMonth());
    }
}
