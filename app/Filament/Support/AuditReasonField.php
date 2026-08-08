<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;

/**
 * "Reason for change" input for forms whose records are audited. The value is
 * never stored on the record itself — CapturesAuditReason lifts it out of the
 * form data and attaches it to the activity log entry instead.
 */
class AuditReasonField
{
    public const KEY = 'audit_reason';

    public static function make(bool $required = false): Textarea
    {
        return Textarea::make(self::KEY)
            ->label(__('Reason for change'))
            ->helperText(__('Recorded in the audit log with this change.'))
            ->rows(2)
            ->maxLength(500)
            ->required($required)
            // Must stay dehydrated so the value reaches the form payload —
            // CapturesAuditReason lifts it out before the record is filled.
            ->visibleOn('edit')
            ->columnSpanFull();
    }
}
