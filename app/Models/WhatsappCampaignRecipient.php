<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCampaignRecipient extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'whatsapp_campaign_id',
        'student_id',
        'name',
        'phone',
        'status',
        'error',
        'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class, 'whatsapp_campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
