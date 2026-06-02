<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAlert;
use App\Models\User;
use App\Settings\AppSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class AttendanceAlertService
{
    public function __construct(protected AppSettings $settings) {}

    /**
     * Run alert checks for every student-status pair just saved in a section.
     *
     * @param  array<int,string> $statuses  student_id => status
     */
    public function checkForSection(Section $section, array $statuses): int
    {
        $alertsFired = 0;

        foreach (array_keys($statuses) as $studentId) {
            $student = Student::find($studentId);
            if (! $student) {
                continue;
            }

            if ($this->settings->enable_absence_alerts) {
                $alertsFired += (int) $this->checkAbsenceStreak($student, $section);
            }

            if ($this->settings->enable_unpaid_attendance_alerts) {
                $alertsFired += (int) $this->checkUnpaidAttendance($student, $section);
            }
        }

        return $alertsFired;
    }

    /**
     * Fire an alert if the student's latest N attendance rows are all 'absent'
     * AND the (N+1)th is NOT absent (or doesn't exist) — meaning the streak
     * just hit the threshold for the first time.
     */
    protected function checkAbsenceStreak(Student $student, Section $section): bool
    {
        $threshold = max(1, $this->settings->absence_alert_threshold);

        $recent = Attendance::query()
            ->where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($threshold + 1)
            ->get();

        if ($recent->count() < $threshold) {
            return false;
        }

        $latestN = $recent->take($threshold);
        if (! $latestN->every(fn (Attendance $a) => $a->status === 'absent')) {
            return false;
        }

        // (N+1)th must not be 'absent' (otherwise streak is longer and we'd
        // already have alerted at the lower threshold).
        $beyond = $recent->get($threshold);
        if ($beyond && $beyond->status === 'absent') {
            return false;
        }

        return $this->fireAlert(
            $student,
            $section,
            StudentAlert::KIND_ABSENCE,
            $threshold,
            __(':name was absent :count consecutive lectures in :section', [
                'name' => is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name,
                'count' => $threshold,
                'section' => is_array($section->name) ? ($section->name[app()->getLocale()] ?? reset($section->name)) : $section->name,
            ])
        );
    }

    /**
     * Fire an alert when a student attends >= threshold lectures in a section
     * while still having an unpaid balance on their registration.
     */
    protected function checkUnpaidAttendance(Student $student, Section $section): bool
    {
        $threshold = max(1, $this->settings->unpaid_attendance_alert_threshold);

        $registration = Registration::query()
            ->where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->first();

        if (! $registration) {
            return false;
        }

        $unpaid = ((float) $registration->amount_due) - ((float) $registration->amount_paid);
        if ($unpaid <= 0) {
            return false;
        }

        $attendedCount = Attendance::query()
            ->where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        if ($attendedCount < $threshold) {
            return false;
        }

        return $this->fireAlert(
            $student,
            $section,
            StudentAlert::KIND_UNPAID,
            $attendedCount,
            __(':name attended :count lectures in :section but still owes :amount', [
                'name' => is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name,
                'count' => $attendedCount,
                'section' => is_array($section->name) ? ($section->name[app()->getLocale()] ?? reset($section->name)) : $section->name,
                'amount' => number_format($unpaid, 2).' ₪',
            ])
        );
    }

    /**
     * Insert an alert row (unique-constraint dedupes) and broadcast a database
     * notification to every admin user. Returns true if a new alert was fired.
     */
    protected function fireAlert(Student $student, Section $section, string $kind, int $thresholdValue, string $body): bool
    {
        $alert = StudentAlert::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'section_id' => $section->id,
                'kind' => $kind,
                'threshold_value' => $thresholdValue,
            ],
            [
                'payload' => ['body' => $body],
                'notified_at' => now(),
            ]
        );

        if (! $alert->wasRecentlyCreated) {
            return false;
        }

        $admins = User::query()->get();
        if ($admins->isEmpty()) {
            return true;
        }

        $title = match ($kind) {
            StudentAlert::KIND_ABSENCE => __('Repeated absences alert'),
            StudentAlert::KIND_UNPAID => __('Unpaid attending student'),
            default => __('Student alert'),
        };

        $color = $kind === StudentAlert::KIND_ABSENCE ? 'danger' : 'warning';

        Notification::make()
            ->{$color}()
            ->icon($kind === StudentAlert::KIND_ABSENCE ? 'heroicon-o-x-circle' : 'heroicon-o-banknotes')
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('view')
                    ->label(__('Open Student'))
                    ->url(\App\Filament\Admin\Resources\Students\StudentResource::getUrl('view', ['record' => $student->id])),
            ])
            ->sendToDatabase($admins);

        return true;
    }
}
