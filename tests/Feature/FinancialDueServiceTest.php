<?php

use App\Models\Attendance;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\FinancialDueService;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name'     => ['en' => 'Fin Trainer', 'ar' => 'مدرب'],
        'username' => 'fin_trainer_' . uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create(['name' => ['en' => 'Maths', 'ar' => 'رياضيات']]);

    $this->section = Section::create([
        'name'                   => ['en' => 'Fin Section', 'ar' => 'قسم مالي'],
        'subject_id'             => $this->subject->id,
        'trainer_id'             => $this->trainer->id,
        'price'                  => 200,
        'trainer_rate'           => 40,
        'fee_type'               => 'per_session',
        'sessions_per_fee_cycle' => 4,
    ]);

    $this->student = Student::create([
        'name'     => ['en' => 'Fin Student', 'ar' => 'طالب مالي'],
        'username' => 'fin_stu_' . uniqid(),
        'password' => 'password',
    ]);

    $this->paymentType = PaymentType::create(['name' => 'Cash']);

    $this->registration = Registration::create([
        'student_id'          => $this->student->id,
        'section_id'          => $this->section->id,
        'payment_type_id'     => $this->paymentType->id,
        'amount_due'          => 200,
        'amount_paid'         => 200,
        'exemption_amount'    => 0,
        'trainer_amount'      => 80,
        'session_offset'      => 0,
        'paid_through_session' => 0,
        'financial_status'    => 'ok',
    ]);
});

// Attendance dates must be >= registration->created_at (today).
// We back-date the registration to ensure our test dates are "after" it.
function addAttendance(int $sectionId, int $studentId, int $daysFromNow = 0): void
{
    Attendance::create([
        'section_id' => $sectionId,
        'student_id' => $studentId,
        'status'     => 'present',
        'date'       => now()->addDays($daysFromNow)->toDateString(),
    ]);
}

it('returns ok when no sessions attended', function () {
    expect(FinancialDueService::computeStatus($this->registration))->toBe('ok');
});

it('returns ok when sessions attended is less than cycle-2', function () {
    addAttendance($this->section->id, $this->student->id, 0);

    expect(FinancialDueService::computeStatus($this->registration))->toBe('ok');
});

it('returns warning when sessionsSince equals cycle - 2', function () {
    // cycle=4 → warning at sessions_since >= 2
    addAttendance($this->section->id, $this->student->id, 0);
    addAttendance($this->section->id, $this->student->id, 1);

    expect(FinancialDueService::computeStatus($this->registration))->toBe('warning');
});

it('returns due when session count reaches cycle', function () {
    for ($i = 0; $i < 4; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    expect(FinancialDueService::computeStatus($this->registration))->toBe('due');
});

it('returns overdue when sessions exceed cycle by 2', function () {
    for ($i = 0; $i < 7; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    expect(FinancialDueService::computeStatus($this->registration))->toBe('overdue');
});

it('returns ok after payment advances paid_through_session', function () {
    for ($i = 0; $i < 5; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    FinancialDueService::recordPayment($this->registration);
    $this->registration->refresh();

    expect(FinancialDueService::computeStatus($this->registration))->toBe('ok');
    expect($this->registration->paid_through_session)->toBe(4);
});

it('refreshAllStatuses updates registration status and returns count', function () {
    for ($i = 0; $i < 5; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    $updated = FinancialDueService::refreshAllStatuses();
    $this->registration->refresh();

    expect($updated)->toBeGreaterThanOrEqual(1);
    expect($this->registration->financial_status)->toBeIn(['due', 'overdue', 'warning']);
});

it('refreshAllStatuses dry-run does not persist changes', function () {
    for ($i = 0; $i < 5; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    FinancialDueService::refreshAllStatuses(dryRun: true);
    $this->registration->refresh();

    expect($this->registration->financial_status)->toBe('ok'); // unchanged
});

it('returns ok for fixed_course fee type', function () {
    $this->section->update(['fee_type' => 'fixed_course']);
    $this->registration->unsetRelation('section'); // clear cached relation

    for ($i = 0; $i < 10; $i++) {
        addAttendance($this->section->id, $this->student->id, $i);
    }

    expect(FinancialDueService::computeStatus($this->registration))->toBe('ok');
});
