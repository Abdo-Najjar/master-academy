<?php

namespace App\Filament\Support;

use App\Support\AuditReason;

/**
 * Add to any Filament Edit page whose form contains an AuditReasonField: the
 * typed reason is pulled out of the payload and stashed so the activity log
 * entry written during the save carries it.
 */
trait CapturesAuditReason
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        AuditReason::set(is_string($data[AuditReasonField::KEY] ?? null) ? $data[AuditReasonField::KEY] : null);

        unset($data[AuditReasonField::KEY]);

        return $data;
    }
}
