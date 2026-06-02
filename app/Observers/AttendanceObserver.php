<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Services\AttendanceAlertService;

class AttendanceObserver
{
    public function __construct(protected AttendanceAlertService $alerts) {}

    public function saved(Attendance $attendance): void
    {
        $attendance->loadMissing('section');
        if ($attendance->section) {
            $this->alerts->checkForSection(
                $attendance->section,
                [$attendance->student_id => $attendance->status]
            );
        }
    }
}
