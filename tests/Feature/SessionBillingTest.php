<?php

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SectionWithdrawalService;
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

    // 100 ₪ every 6 sessions held, collected by hand — this file covers the
    // derived counter and the due/overdue ladder on their own. Sections that
    // charge the cycle themselves are covered in PerSessionAutoChargeTest.
    $this->section = Section::create([
        'name' => 'شعبة بالحصة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 6,
        'cycle_fee' => 100,
        'auto_charge_cycles' => false,
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
    holdSessions($this->section, 3, '2026-08-01');

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
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
    // Paid at the desk before the charge, the way enrolling really goes. The
    // ladder below is about the session counter, so the bill is kept covered —
    // an unfunded one would raise the status on its own.
    $this->student->depositFloat(100);

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

    // The break is a window of dates, so lessons held inside it stay off the
    // bill for good — even once the student is back and the counter is
    // recomputed from scratch.
    SessionBillingService::pause($registration->fresh(), '2026-09-05');
    holdSessions($this->section, 3, '2026-09-10');
    expect($registration->fresh()->sessions_counted)->toBe(2);

    SessionBillingService::resume($registration->fresh(), '2026-09-15');
    holdSessions($this->section, 1, '2026-09-20');
    expect($registration->fresh()->sessions_counted)->toBe(3);
});

it('extends the paid horizon and charges the wallet when a cycle is collected', function () {
    // Two cycles' worth handed over at the desk: the first buys the enrolment,
    // the second is sitting as credit when the next cycle is collected.
    $this->student->depositFloat(200);

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
        // The credit on the wallet covered the new cycle as it was charged.
        ->and((float) $registration->funded_amount)->toBe(200.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(0.0)
        ->and($registration->financial_status)->toBe('ok');
});

it('will not call a collected cycle paid while the wallet has not covered it', function () {
    // Same collection, but nothing was ever handed over: the horizon moves and
    // the counter is happy, yet 200 ₪ is owed. The badge has to say so — this
    // is the case that used to read "paid" against a wallet at minus 200.
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 6);
    SessionBillingService::payCycle($registration->fresh());

    $registration = $registration->fresh();

    expect($registration->paid_through_session)->toBe(12)
        ->and(SessionBillingService::remainingSessions($registration))->toBe(6)
        ->and((float) $registration->funded_amount)->toBe(0.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-200.0)
        ->and($registration->financial_status)->toBe('overdue');
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

/**
 * The scenario a centre hits the first time it puts its history into the
 * system: every student is typed in first, then three months of lessons.
 */
it('does not charge backdated lessons to a student who joined later', function () {
    $early = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-06-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    $late = Registration::create([
        'student_id' => Student::create([
            'name' => ['ar' => 'طالب متأخر', 'en' => 'Late Student'],
            'username' => 'late_student_'.uniqid(),
            'password' => 'password',
            'status' => 'active',
        ])->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // Three months of history, entered after both students already exist.
    holdSessions($this->section, 4, '2026-06-10');
    holdSessions($this->section, 4, '2026-07-10');
    holdSessions($this->section, 4, '2026-08-10');

    expect($early->fresh()->sessions_counted)->toBe(12)
        // Only the lessons from August — the earlier ones were not theirs.
        ->and($late->fresh()->sessions_counted)->toBe(4);
});

it('charges lessons already on record to a student enrolled afterwards', function () {
    // The other entry order: the lessons go in first, then a registration
    // backdated to before some of them.
    holdSessions($this->section, 5, '2026-07-01');

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-07-03',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // Held on the 1st, 2nd, 3rd, 4th and 5th — the student joined on the 3rd.
    expect($registration->fresh()->sessions_counted)->toBe(3)
        ->and($registration->fresh()->session_offset)->toBe(2);
});

it('re-decides which lessons are charged when the enrollment date is corrected', function () {
    holdSessions($this->section, 6, '2026-07-01');

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-07-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    expect($registration->fresh()->sessions_counted)->toBe(6);

    $registration->update(['enrolled_at' => '2026-07-04']);

    expect($registration->fresh()->sessions_counted)->toBe(3);
});

it('does not count a lesson the student was excused from', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 3);
    expect($registration->fresh()->sessions_counted)->toBe(3);

    Attendance::recordDay($this->section->id, '2026-09-02', [
        $this->student->id => 'excused',
    ]);

    // The apology takes that one lesson back off their bill.
    expect($registration->fresh()->sessions_counted)->toBe(2);

    // …and switching the status back puts it on again.
    Attendance::recordDay($this->section->id, '2026-09-02', [
        $this->student->id => 'present',
    ]);

    expect($registration->fresh()->sessions_counted)->toBe(3);
});

it('keeps an excused lesson off the bill when the section is recalculated', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 4);

    Attendance::recordDay($this->section->id, '2026-09-01', [$this->student->id => 'excused']);
    Attendance::recordDay($this->section->id, '2026-09-03', [$this->student->id => 'absent']);

    // An absence still counts; only the apology does not.
    expect($registration->fresh()->sessions_counted)->toBe(3);

    SessionBillingService::recountSection($this->section->id);

    expect($registration->fresh()->sessions_counted)->toBe(3);
});

it('does not record attendance for a student who had not joined yet', function () {
    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    Attendance::recordDay($this->section->id, '2026-08-20', [
        $this->student->id => 'absent',
    ]);

    expect(Attendance::query()->where('student_id', $this->student->id)->count())->toBe(0);
});

it('recalculates a whole section back to the right numbers', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 5);

    // Whatever the counter drifted to, recalculating puts it back.
    $registration->forceFill(['sessions_counted' => 99])->saveQuietly();

    $recounted = SessionBillingService::recountSection($this->section->id);

    expect($recounted)->toBe(1)
        ->and($registration->fresh()->sessions_counted)->toBe(5);
});

it('stops counting for good when a student withdraws from the section', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 4);
    expect($registration->fresh()->sessions_counted)->toBe(4);

    // Recorded after the fact: they stopped coming on the 3rd.
    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-03', 'سافر');

    // The lessons on the 1st and 2nd stand; the 3rd and 4th are not theirs.
    expect($registration->fresh()->sessions_counted)->toBe(2);

    // Lessons held after they left never touch them again.
    holdSessions($this->section, 5, '2026-09-20');
    expect($registration->fresh()->sessions_counted)->toBe(2);

    // …and neither does recalculating the whole section.
    SessionBillingService::recountSection($this->section->id);
    expect($registration->fresh()->sessions_counted)->toBe(2);
});

it('puts the missed lessons back on the bill when a withdrawal is undone', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 5);

    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-03');
    expect($registration->fresh()->sessions_counted)->toBe(2);

    SectionWithdrawalService::rejoin($registration->fresh());

    expect($registration->fresh()->sessions_counted)->toBe(5)
        ->and($registration->fresh()->left_at)->toBeNull();
});

it('reports the paid sessions a withdrawn student will never use', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 2);

    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-03');

    // Paid for 6, used 2.
    expect($registration->fresh()->unusedPaidSessions())->toBe(4);
});

it('closes an open break when the student leaves outright', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 6);

    SessionBillingService::pause($registration->fresh(), '2026-09-03');
    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-05');

    $stored = $registration->fresh();

    expect($stored->paused_at)->toBeNull()
        ->and($stored->pauses()->open()->count())->toBe(0)
        // The break already stopped the counting on the 3rd, so leaving on the
        // 5th changes nothing about what was charged.
        ->and($stored->sessions_counted)->toBe(2);
});

it('takes a withdrawn student off the attendance sheet from the day they left', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
    ]);

    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-03');

    expect($this->section->rosterOn('2026-09-02')->pluck('student_id')->all())
        ->toBe([$this->student->id])
        ->and($this->section->rosterOn('2026-09-03')->pluck('student_id')->all())
        ->toBeEmpty();

    // And attendance for a day after they left is refused outright.
    Attendance::recordDay($this->section->id, '2026-09-10', [$this->student->id => 'absent']);

    expect(Attendance::query()->where('student_id', $this->student->id)->count())->toBe(0);
});

it('withdraws every section when the student leaves the centre', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    holdSessions($this->section, 6);
    expect($registration->fresh()->sessions_counted)->toBe(6);

    $this->student->update([
        'status' => 'withdrawn',
        'withdrawal_date' => '2026-09-04',
        'withdrawal_reason' => 'انتقل لمدينة أخرى',
    ]);

    $stored = $registration->fresh();

    expect($stored->left_at?->toDateString())->toBe('2026-09-04')
        ->and($stored->sessions_counted)->toBe(3);

    // Coming back re-opens the sections that leaving the centre had closed.
    $this->student->update(['status' => 'active']);

    expect($registration->fresh()->left_at)->toBeNull()
        ->and($registration->fresh()->sessions_counted)->toBe(6);
});
