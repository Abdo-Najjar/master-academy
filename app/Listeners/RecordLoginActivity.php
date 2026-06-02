<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Support\UserAgentParser;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class RecordLoginActivity
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user) {
            return;
        }

        $ua = Request::userAgent();
        $parsed = UserAgentParser::parse($ua);

        LoginActivity::create([
            'auth_type' => $user->getMorphClass(),
            'auth_id' => $user->getKey(),
            'guard' => $event->guard,
            'ip' => Request::ip(),
            'user_agent' => $ua,
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'device' => $parsed['device'],
            'logged_in_at' => now(),
        ]);
    }
}
