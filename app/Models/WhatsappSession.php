<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappSession extends Model
{
    use SoftDeletes;

    public const STATUS_INITIALIZING = 'initializing';
    public const STATUS_QR_READY     = 'qr_ready';
    public const STATUS_READY        = 'ready';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR        = 'error';

    protected $fillable = [
        'unique_id',
        'status',
        'phone_number',
        'name',
        'profile_picture_path',
        'qr_code',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];

    public function scopeLinked($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
