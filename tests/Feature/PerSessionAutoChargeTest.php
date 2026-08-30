<?php

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SectionWithdrawalService;
use App\Services\TrainerPayoutService;
use Carbon\Carbon;

/**
 * The centre's headline case: a section priced "100 ₪ every 8 lessons". The
 * money must come off the student's wallet the moment the eighth lesson is
 * recorded, without anyone opening a payment dialog.
 */
beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب', 'en' => 'Trainer'],
        'username' => 'auto_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة كل 8 حصص',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 100,
        'start_date' => '2026-09-01',
        'end_date' => '2027-06-30',
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'auto_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
});

/** Record attendance for `$count` consecutive lesson days. */
function takeAttendanceFor(Section $section, int $studentId, int $count, string $from = '2026-09-01'): void
{
    for ($i = 0; $i < $count; $i++) {
        Attendance::recordDay(
            $section->id,
            Carbon::parse($from)->addDays($i)->toDateString(),
            [$studentId => 'present'],
        );
    }
}

it('charges the next cycle automatically once the eighth lesson is recorded', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // The up-front 100 ₪ bought the first eight lessons.
    expect($registration->fresh()->paid_through_session)->toBe(8)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-100.0);

    // Seven lessons in: still inside the paid cycle, nothing extra charged.
    takeAttendanceFor($this->section, $this->student->id, 7);

    expect($registration->fresh()->sessions_counted)->toBe(7)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-100.0);

    // The eighth closes the cycle — the next 100 ₪ comes off the wallet.
    takeAttendanceFor($this->section, $this->student->id, 1, '2026-09-08');

    $stored = $registration->fresh();

    expect($stored->sessions_counted)->toBe(8)
        ->and($stored->paid_through_session)->toBe(16)
        ->and((float) $stored->amount_paid)->toBe(200.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-200.0)
        // Eight lessons still in hand, but 200 ₪ was billed and none of it has
        // been handed over. Charging a cycle pushes the horizon forward on its
        // own, so the counter alone would call this "paid" against a wallet at
        // minus 200 — the badge follows the money instead.
        ->and((float) $stored->funded_amount)->toBe(0.0)
        ->and($stored->financial_status)->toBe('overdue');

    // And it settles the moment the family actually pays.
    $this->student->depositFloat(200);
    TrainerPayoutService::settleForStudent($this->student->fresh(), 200);

    expect($registration->fresh()->financial_status)->toBe('ok')
        ->and((float) $registration->fresh()->funded_amount)->toBe(200.0);
});

it('does not charge a student who was excused from the lesson that closes the cycle', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    takeAttendanceFor($this->section, $this->student->id, 7);

    // The eighth lesson is held, but this student apologised for it — so it is
    // not theirs, the cycle is not used up, and no money moves.
    Attendance::recordDay($this->section->id, '2026-09-08', [
        $this->student->id => 'excused',
    ]);

    $stored = $registration->fresh();

    expect($stored->sessions_counted)->toBe(7)
        ->and($stored->paid_through_session)->toBe(8)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-100.0);

    // Correcting the apology to a real attendance charges it after all.
    Attendance::recordDay($this->section->id, '2026-09-08', [
        $this->student->id => 'present',
    ]);

    expect($registration->fresh()->paid_through_session)->toBe(16)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-200.0);
});

it('leaves the money alone when the section collects by hand', function () {
    $this->section->update(['auto_charge_cycles' => false]);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    takeAttendanceFor($this->section, $this->student->id, 8);

    $stored = $registration->fresh();

    // Counted and flagged, but nothing charged — the old behaviour, kept as
    // an opt-out for centres that take cash at the desk.
    expect($stored->sessions_counted)->toBe(8)
        ->and($stored->paid_through_session)->toBe(8)
        ->and($stored->financial_status)->toBe('due')
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-100.0);
});

it('charges every cycle a backlog of lessons used up, and credits the trainer', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // 24 lessons typed in at once. A cycle is charged the moment the previous
    // one is used up — at lessons 8, 16 and 24 — so three further cycles are
    // taken and the student is covered through lesson 32.
    takeAttendanceFor($this->section, $this->student->id, 24);

    $stored = $registration->fresh();

    expect($stored->sessions_counted)->toBe(24)
        ->and($stored->paid_through_session)->toBe(32)
        ->and((float) $stored->amount_paid)->toBe(400.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-400.0)
        // 40% of everything charged, not just of the first cycle.
        ->and((float) $stored->trainer_amount)->toBe(160.0);
});

it('stops charging a student who has left the section', function () {
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    takeAttendanceFor($this->section, $this->student->id, 4);

    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-05');

    takeAttendanceFor($this->section, $this->student->id, 8, '2026-09-06');

    expect($registration->fresh()->paid_through_session)->toBe(8)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(-100.0);
});
