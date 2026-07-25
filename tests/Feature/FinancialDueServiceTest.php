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
        'name'       => 'Fin Section',
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

    // The student's wallet starts empty, so this charge lands unfunded
    // ("overdue") even though amount_paid equals the full fee.
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

it('computes remainingBalance as the unfunded portion of amount_paid', function () {
    // amount_paid is charged to the wallet in full at creation regardless of
    // balance; funded_amount tracks how much of that charge real money backs.
    $this->registration->forceFill(['funded_amount' => 120])->saveQuietly();

    expect(FinancialDueService::remainingBalance($this->registration->fresh()))->toBe(80.0);
});

it('returns overdue right after creation when the student had no funds to cover the charge', function () {
    // The student's wallet was empty before this registration, so nothing
    // could be funded even though amount_paid was set to the full fee.
    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('overdue');
});

it('returns ok when the charge is fully funded', function () {
    $this->registration->forceFill(['funded_amount' => 200])->saveQuietly();

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('ok');
});

it('returns ok when amount_paid is zero regardless of funded_amount', function () {
    $this->registration->forceFill(['amount_paid' => 0, 'funded_amount' => 0])->saveQuietly();

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('ok');
});

it('returns due when partially funded', function () {
    $this->registration->forceFill(['funded_amount' => 80])->saveQuietly();

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('due');
});

it('returns overdue when nothing has been funded on a charge with a balance', function () {
    $this->registration->forceFill(['funded_amount' => 0])->saveQuietly();

    expect(FinancialDueService::computeStatus($this->registration->fresh()))->toBe('overdue');
});

it('refreshAllStatuses updates a stale financial_status and returns the changed count', function () {
    // Stamp a stale "ok" directly, bypassing the observer, while funded_amount
    // (0) is still short of amount_paid (200) — exactly the mismatch
    // refreshAllStatuses reconciles.
    $this->registration->forceFill(['financial_status' => 'ok'])->saveQuietly();
    expect($this->registration->fresh()->financial_status)->toBe('ok');

    $updated = FinancialDueService::refreshAllStatuses();

    expect($updated)->toBe(1)
        ->and($this->registration->fresh()->financial_status)->toBe('overdue');
});

it('refreshAllStatuses dry-run does not persist changes', function () {
    $this->registration->forceFill(['financial_status' => 'ok'])->saveQuietly();

    $updated = FinancialDueService::refreshAllStatuses(dryRun: true);

    expect($updated)->toBe(1)
        ->and($this->registration->fresh()->financial_status)->toBe('ok');
});
