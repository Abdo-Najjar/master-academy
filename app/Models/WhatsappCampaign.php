<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'message',
        'student_group_id',
        'status',
        'total_count',
        'sent_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function studentGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappCampaignRecipient::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
