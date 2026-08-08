<?php

namespace App\Observers;

use App\Models\SectionSession;
use App\Services\SessionBillingService;

class SectionSessionObserver
{
    public function creating(SectionSession $session): void
    {
        // A private lesson is billed on its own and must never advance the
        // regular session counter, whatever the operator ticked.
        if ($session->type === SectionSession::TYPE_PRIVATE) {
            $session->counts_toward_billing = false;
        }

        if (! $session->created_by && auth()->check()) {
            $session->created_by = auth()->id();
        }
    }

    public function updating(SectionSession $session): void
    {
        if ($session->type === SectionSession::TYPE_PRIVATE) {
            $session->counts_toward_billing = false;
        }

        // Cancelling a lesson must not silently keep an old reason around.
        if ($session->isDirty('status') && $session->status !== SectionSession::STATUS_CANCELLED) {
            $session->cancellation_reason = null;
        }
    }

    public function created(SectionSession $session): void
    {
        SessionBillingService::syncSession($session);
    }

    public function updated(SectionSession $session): void
    {
        if ($session->wasChanged(['status', 'type', 'counts_toward_billing'])) {
            SessionBillingService::syncSession($session);
        }
    }

    public function deleted(SectionSession $session): void
    {
        SessionBillingService::revertSession($session);
    }
}
