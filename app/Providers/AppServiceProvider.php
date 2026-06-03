<?php

namespace App\Providers;

use App\Listeners\RecordLoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordLoginActivity::class);

        // Super admin: the admin User with ID 1 bypasses every permission gate
        // (HexaLite gates resolve through the Gate facade, so this covers them too).
        // Scoped to the User model so a Student/Trainer that happens to have id 1
        // on another guard does not gain access.
        Gate::before(function ($user) {
            return ($user instanceof User && (int) $user->getKey() === 1) ? true : null;
        });
    }
}
