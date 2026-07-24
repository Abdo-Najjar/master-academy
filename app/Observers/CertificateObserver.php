<?php

namespace App\Observers;

use App\Models\Certificate;
use Illuminate\Support\Str;

class CertificateObserver
{
    public function creating(Certificate $certificate): void
    {
        if (empty($certificate->serial_number)) {
            $year = now()->year;
            $next = (int) Certificate::query()->withTrashed()->whereYear('created_at', $year)->count() + 1;
            $certificate->serial_number = 'MA-'.$year.'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        }
        if (empty($certificate->verification_token)) {
            $certificate->verification_token = Str::uuid()->toString();
        }
        if (empty($certificate->issued_at)) {
            $certificate->issued_at = now();
        }
    }
}
