<?php

namespace App\Livewire\Concerns;

/**
 * Floating toast notifications for the student/trainer portals, so a success
 * message is seen even when the user is scrolled far down the page — the same
 * behaviour the Filament admin panel already has.
 *
 * Pair with `@include('livewire.partials.portal-toast')` in the component view.
 */
trait NotifiesPortal
{
    protected function portalToast(string $message, string $type = 'success'): void
    {
        $this->dispatch('portal-toast', message: $message, type: $type);
    }
}
