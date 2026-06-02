<?php

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

it('records a login activity when the Login event fires', function () {
    $user = User::factory()->create();
    $countBefore = LoginActivity::count();

    Event::dispatch(new Login('web', $user, false));

    expect(LoginActivity::count())->toBeGreaterThan($countBefore);

    $activity = LoginActivity::where('auth_id', $user->id)
        ->where('auth_type', $user->getMorphClass())
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->guard)->toBe('web');
    expect($activity->logged_in_at)->not->toBeNull();
});

it('captures browser metadata from request user-agent', function () {
    $user = User::factory()->create();

    $this->withServerVariables([
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '203.0.113.5',
    ]);

    // Simulate any request so request()->userAgent() / ip() are populated
    $this->get('/');

    Event::dispatch(new Login('web', $user, false));

    $activity = LoginActivity::first();
    expect($activity->browser)->toBe('Chrome');
    expect($activity->platform)->toBe('Windows 10/11');
    expect($activity->device)->toBe('Desktop');
});
