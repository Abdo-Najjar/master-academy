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

    public const TARGET_GROUP = 'group';

    public const TARGET_SECTION = 'section';

    public const TARGET_SUBJECT = 'subject';

    public const TARGET_TRAINER = 'trainer';

    public const TARGET_ALL = 'all';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'message',
        'student_group_id',
        'target_type',
        'target_id',
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

    /** @return array<string, string> */
    public static function targetTypeOptions(): array
    {
        return [
            self::TARGET_GROUP => __('Student Group'),
            self::TARGET_SECTION => __('Section'),
            self::TARGET_SUBJECT => __('Course'),
            self::TARGET_TRAINER => __('Trainer'),
            self::TARGET_ALL => __('All active students'),
        ];
    }

    /** Human-readable description of who this campaign goes to. */
    public function targetLabel(): string
    {
        $locale = app()->getLocale();

        return match ($this->target_type) {
            self::TARGET_SECTION => (string) (Section::find($this->target_id)?->name ?? '—'),
            self::TARGET_SUBJECT => (string) (Subject::find($this->target_id)?->getTranslation('name', $locale, false) ?? '—'),
            self::TARGET_TRAINER => (string) (Trainer::find($this->target_id)?->getTranslation('name', $locale, false) ?? '—'),
            self::TARGET_ALL => __('All active students'),
            default => (string) ($this->studentGroup?->name ?? '—'),
        };
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
