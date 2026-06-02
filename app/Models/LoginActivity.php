<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LoginActivity extends Model
{
    protected $fillable = [
        'auth_type',
        'auth_id',
        'guard',
        'ip',
        'browser',
        'platform',
        'device',
        'user_agent',
        'logged_in_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
        ];
    }

    public function auth(): MorphTo
    {
        return $this->morphTo();
    }
}
