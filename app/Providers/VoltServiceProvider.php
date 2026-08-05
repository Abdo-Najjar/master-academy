<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Volt is only consulted via Livewire::resolveMissingComponent, so the
        // existing class-based components under views/livewire keep resolving
        // to their classes first; only views/livewire/site is Volt-backed.
        Volt::mount([
            resource_path('views/livewire'),
        ]);
    }
}
