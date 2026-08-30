<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Support\CycleBreakdown;

/**
 * The balance table used to show three bare totals — charged, paid, remaining —
 * on courses that are sold and argued about by the month. Whoever read it had
 * to divide by the monthly fee before they could answer "how many months is he
 * behind", which is the only question anyone asks it.
 */
beforeEach(function () {
    app()->setLocale('ar');

    $this->section = Section::create([
        'name' => 'شعبة شهرية',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 60,
        'auto_charge_cycles' => false,
    ]);
});

test('an amount is broken down into whole months at the monthly fee', function () {
    expect(CycleBreakdown::forAmount($this->section, 120))->toContain('شهران')
        ->and(CycleBreakdown::forAmount($this->section, 180))->toContain('شهور')
        ->and(CycleBreakdown::forAmount($this->section, 60))->toContain('شهر واحد');
});

test('a part-paid amount keeps the leftover instead of rounding it away', function () {
    // 230 is three whole months plus 50 that buys no fourth.
    $caption = CycleBreakdown::forAmount($this->section, 230);

    expect($caption)->toContain('شهور')
        ->and($caption)->toContain('+');
});

test('an amount smaller than one month is shown as itself', function () {
    expect(CycleBreakdown::forAmount($this->section, 20))->not->toContain('شهر');
});

test('a course not priced by the month has nothing to break down', function () {
    $fixed = Section::create([
        'name' => 'شعبة بسعر ثابت',
        'subject_id' => $this->section->subject_id,
        'price' => 500,
        'fee_type' => Section::FEE_TYPE_FIXED_COURSE,
    ]);

    expect(CycleBreakdown::forAmount($fixed, 500))->toBeNull()
        ->and(CycleBreakdown::forAmount($this->section, 0))->toBeNull();
});

test('the outstanding caption names the lessons behind the debt', function () {
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'cycle_break_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => now()->subMonths(2),
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    // Ten lessons taught, eight of them paid for: one month behind.
    $registration->forceFill([
        'sessions_counted' => 10,
        'paid_through_session' => 8,
    ])->saveQuietly();

    $registration->setRelation('section', $this->section);

    $caption = CycleBreakdown::forOutstanding($registration, 60);

    expect($caption)->toContain('شهر واحد')
        ->and($caption)->toContain('غير مدفوعة');
});

test('a course paid up to date quotes no unpaid lessons', function () {
    $student = Student::create([
        'name' => ['ar' => 'طالب مسدد', 'en' => 'Settled Student'],
        'username' => 'cycle_settled_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => now()->subMonth(),
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $registration->forceFill([
        'sessions_counted' => 3,
        'paid_through_session' => 8,
    ])->saveQuietly();

    $registration->setRelation('section', $this->section);

    expect(CycleBreakdown::forOutstanding($registration, 60))->not->toContain('غير مدفوعة');
});
