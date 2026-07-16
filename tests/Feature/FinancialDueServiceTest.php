<?php

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
        'username' => 'fin_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create(['name' => ['en' => 'Maths', 'ar' => 'رياضيات']]);

    $this->section = Section::create([
        'name'       => ['en' => 'Fin Section', 'ar' => 'قسم مالي'],
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price'      => 200,
    ]);

    $this->student = Student::create([
        'name'     => ['en' => 'Fin Student', 'ar' => 'طالب مالي'],
        'username' => 'fin_stu_'.uniqid(),
        'password' => 'password',
    ]);

    $this->paymentType = PaymentType::create(['name' => 'Cash']);

    // RegistrationObserver::creating() computes financial_status automatically —
    // a fully-paid course fee lands as "ok".
    $this->registration = Registration::create([
        'student_id'       => $this->student->id,
        'section_id'       => $this->section->id,
        'payment_type_id'  => $this->paymentType->id,
        'amount_due'       => 200,
        'amount_paid'      => 200,
        'exemption_amount' => 0,
        'trainer_amount'   => 80,
    ]);
});

it('computes remainingBalance as due minus paid minus exemption', function () {
    $this->registration->updateQuietly(['amount_due' => 200, 'amount_paid' => 120, 'exemption_amount' => 30]);

    expect(FinancialDueService::remainingBalance($this->registration->fresh()))->toBe(50.0);
});

it('returns ok when fully paid', function () {
    expect(FinancialDueService::computeStatus($this->registration))->toBe('ok');
});

it('returns ok when exemption covers the remaining balance', function () {
    $this->registration->updateQuietly(['amount_due' => 200, 'amount_paid' => 0, 'exemption_amount' => 200]);

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('ok');
});

it('returns due when partially paid', function () {
    $this->registration->updateQuietly(['amount_due' => 200, 'amount_paid' => 80, 'exemption_amount' => 0]);

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('due');
});

it('returns overdue when nothing has been paid on a course with a fee', function () {
    $this->registration->updateQuietly(['amount_due' => 200, 'amount_paid' => 0, 'exemption_amount' => 0]);

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('overdue');
});

it('refreshAllStatuses updates a stale financial_status and returns the changed count', function () {
    // Bypass the observer so financial_status is left stale at "ok" while the
    // balance now shows a due amount — exactly what refreshAllStatuses fixes.
    $this->registration->updateQuietly(['amount_paid' => 0]);
    expect($this->registration->fresh()->financial_status)->toBe('ok');

    $updated = FinancialDueService::refreshAllStatuses();

    expect($updated)->toBe(1)
        ->and($this->registration->fresh()->financial_status)->toBe('overdue');
});

it('refreshAllStatuses dry-run does not persist changes', function () {
    $this->registration->updateQuietly(['amount_paid' => 0]);

    $updated = FinancialDueService::refreshAllStatuses(dryRun: true);

    expect($updated)->toBe(1)
        ->and($this->registration->fresh()->financial_status)->toBe('ok');
});
