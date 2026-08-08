<?php

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SessionBillingService;
use Carbon\Carbon;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب الحصص', 'en' => 'Sessions Trainer'],
        'username' => 'sessions_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة الحصص', 'en' => 'Sessions Subject']]);

    // 100 ₪ every 6 sessions held.
    $this->section = Section::create([
        'name' => 'شعبة بالحصة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 6,
        'cycle_fee' => 100,
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب الحصص', 'en' => 'Sessions Student'],
        'username' => 'sessions_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
});

/** Mark `$count` regular sessions as held for the section. */
function holdSessions(Section $section, int $count, string $startDate = '2026-09-01'): void
{
    for ($i = 0; $i < $count; $i++) {
        SectionSession::create([
            'section_id' => $section->id,
            'date' => Carbon::parse($startDate)->addDays($i)->toDateString(),
            'type' => SectionSession::TYPE_REGULAR,
            'status' => SectionSession::STATUS_HELD,
        ]);
    }
}

it('starts counting from the session after the student joined', function () {
    holdSessions($this->section, 3);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // Three lessons already happened before enrolment, so they are not charged.
    $stored = $registration->fresh();

    expect($stored->session_offset)->toBe(3)
        ->and($stored->sessions_counted)->toBe(0)
        ->and($stored->paid_through_session)->toBe(6);

    holdSessions($this->section, 1, '2026-09-10');

    expect($registration->fresh()->sessions_counted)->toBe(1);
});

it('warns two sessions before the cycle ends, then marks due and overdue', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    expect($registration->fresh()->financial_status)->toBe('ok');

    // 4 of 6 held -> 2 sessions left -> warning.
    holdSessions($this->section, 4);
    expect($registration->fresh()->financial_status)->toBe('warning');

    // 6 of 6 held -> the cycle is over -> due.
    holdSessions($this->section, 2, '2026-09-10');
    expect($registration->fresh()->financial_status)->toBe('due');

    // Two more lessons attended without paying -> overdue.
    holdSessions($this->section, 2, '2026-09-20');
    expect($registration->fresh()->financial_status)->toBe('overdue');
});

it('absence does not stop a session from being counted', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 2);

    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'date' => '2026-09-01',
        'status' => 'absent',
    ]);

    expect($registration->fresh()->sessions_counted)->toBe(2);
});

it('does not count cancelled or private sessions, and rolls back a cancellation', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    $held = SectionSession::create([
        'section_id' => $this->section->id,
        'date' => '2026-09-01',
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
    ]);

    SectionSession::create([
        'section_id' => $this->section->id,
        'date' => '2026-09-02',
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_CANCELLED,
        'cancellation_reason' => 'عطلة',
    ]);

    SectionSession::create([
        'section_id' => $this->section->id,
        'date' => '2026-09-03',
        'type' => SectionSession::TYPE_PRIVATE,
        'status' => SectionSession::STATUS_HELD,
        'fee' => 50,
    ]);

    expect($registration->fresh()->sessions_counted)->toBe(1);

    // Cancelling a lesson that already counted takes it back off the counter.
    $held->update(['status' => SectionSession::STATUS_CANCELLED, 'cancellation_reason' => 'مرض المدرب']);

    expect($registration->fresh()->sessions_counted)->toBe(0);
});

it('counts a makeup session toward the cycle', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    SectionSession::create([
        'section_id' => $this->section->id,
        'date' => '2026-09-05',
        'type' => SectionSession::TYPE_MAKEUP,
        'status' => SectionSession::STATUS_HELD,
    ]);

    expect($registration->fresh()->sessions_counted)->toBe(1);
});

it('pauses counting while the registration is paused and resumes from the same point', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 2);
    expect($registration->fresh()->sessions_counted)->toBe(2);

    SessionBillingService::pause($registration->fresh());
    holdSessions($this->section, 3, '2026-09-10');
    expect($registration->fresh()->sessions_counted)->toBe(2);

    SessionBillingService::resume($registration->fresh());
    holdSessions($this->section, 1, '2026-09-20');
    expect($registration->fresh()->sessions_counted)->toBe(3);
});

it('extends the paid horizon and charges the wallet when a cycle is collected', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 6);
    expect($registration->fresh()->financial_status)->toBe('due');

    SessionBillingService::payCycle($registration->fresh());

    $registration = $registration->fresh();

    expect($registration->paid_through_session)->toBe(12)
        ->and((float) $registration->amount_paid)->toBe(200.0)
        ->and($registration->financial_status)->toBe('ok');
});

it('charges a private session fee to the chosen students and credits the trainer', function () {
    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $session = SectionSession::create([
        'section_id' => $this->section->id,
        'date' => '2026-09-01',
        'type' => SectionSession::TYPE_PRIVATE,
        'status' => SectionSession::STATUS_HELD,
        'fee' => 80,
        'trainer_rate' => 50,
    ]);

    $charged = SessionBillingService::chargePrivateSession($session, [$this->student->id]);

    expect($charged)->toBe(1)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-80.0)
        // Its own rate (50%), not the section's.
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(40.0);
});

it('leaves fixed-course sections on the funding-based status', function () {
    $fixed = Section::create([
        'name' => 'شعبة دورة كاملة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 500,
        'fee_type' => Section::FEE_TYPE_FIXED_COURSE,
    ]);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $fixed->id,
        'amount_due' => 500,
        'amount_paid' => 500,
    ]);

    holdSessions($fixed, 10);

    // No session counting at all on a fixed-course section.
    expect($registration->fresh()->sessions_counted)->toBe(0)
        ->and($registration->fresh()->financial_status)->toBe('overdue');
});
