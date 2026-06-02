<?php

use App\Models\Attendance;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAlert;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\AttendanceAlertService;
use App\Settings\AppSettings;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Alert Trainer', 'ar' => 'مدرب التنبيه'],
        'username' => 'alert_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create([
        'name' => ['en' => 'Alerts Subject', 'ar' => 'مادة التنبيهات'],
    ]);

    $this->section = Section::create([
        'name' => ['en' => 'Alerts Section', 'ar' => 'قسم التنبيهات'],
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
        'trainer_rate' => 50,
    ]);

    $this->student = Student::create([
        'name' => ['en' => 'Alert Student', 'ar' => 'طالب التنبيه'],
        'username' => 'alert_'.uniqid(),
        'password' => 'password',
    ]);

    // Use generous thresholds so we can drive both code paths
    $settings = app(AppSettings::class);
    $settings->enable_absence_alerts = true;
    $settings->absence_alert_threshold = 3;
    $settings->enable_unpaid_attendance_alerts = true;
    $settings->unpaid_attendance_alert_threshold = 2;
    $settings->save();
});

function recordAttendance(int $sectionId, int $studentId, string $status, string $date): Attendance
{
    return Attendance::create([
        'section_id' => $sectionId,
        'student_id' => $studentId,
        'status' => $status,
        'date' => $date,
    ]);
}

it('fires an absence alert after the threshold of consecutive absences', function () {
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-20');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-21');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-22');

    app(AttendanceAlertService::class)
        ->checkForSection($this->section, [$this->student->id => 'absent']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_ABSENCE)->count())->toBe(1);
});

it('does not fire absence alert if streak is shorter than threshold', function () {
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-21');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-22');

    app(AttendanceAlertService::class)
        ->checkForSection($this->section, [$this->student->id => 'absent']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_ABSENCE)->count())->toBe(0);
});

it('does not re-fire absence alert at the same threshold (dedupe)', function () {
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-20');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-21');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-22');

    $svc = app(AttendanceAlertService::class);
    $svc->checkForSection($this->section, [$this->student->id => 'absent']);
    $svc->checkForSection($this->section, [$this->student->id => 'absent']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_ABSENCE)->count())->toBe(1);
});

it('fires an unpaid attendance alert when student attends without paying', function () {
    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'payment_type_id' => PaymentType::create(['name' => 'Cash'])->id,
        'amount_due' => 100,
        'amount_paid' => 20,
        'exemption_amount' => 0,
        'trainer_amount' => 0,
    ]);

    recordAttendance($this->section->id, $this->student->id, 'present', '2026-05-20');
    recordAttendance($this->section->id, $this->student->id, 'present', '2026-05-21');

    app(AttendanceAlertService::class)
        ->checkForSection($this->section, [$this->student->id => 'present']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_UNPAID)->count())->toBe(1);
});

it('does not fire unpaid alert when registration is fully paid', function () {
    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'payment_type_id' => PaymentType::create(['name' => 'Cash'])->id,
        'amount_due' => 100,
        'amount_paid' => 100,
        'exemption_amount' => 0,
        'trainer_amount' => 0,
    ]);

    recordAttendance($this->section->id, $this->student->id, 'present', '2026-05-20');
    recordAttendance($this->section->id, $this->student->id, 'present', '2026-05-21');

    app(AttendanceAlertService::class)
        ->checkForSection($this->section, [$this->student->id => 'present']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_UNPAID)->count())->toBe(0);
});

it('respects the enable_absence_alerts flag', function () {
    $settings = app(AppSettings::class);
    $settings->enable_absence_alerts = false;
    $settings->save();
    app()->forgetInstance(AppSettings::class);

    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-20');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-21');
    recordAttendance($this->section->id, $this->student->id, 'absent', '2026-05-22');

    app(AttendanceAlertService::class)
        ->checkForSection($this->section, [$this->student->id => 'absent']);

    expect(StudentAlert::where('kind', StudentAlert::KIND_ABSENCE)->count())->toBe(0);
});
