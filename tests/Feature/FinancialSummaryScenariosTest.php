<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\PaymentAllocationService;
use App\Services\SectionWithdrawalService;
use App\Services\SessionBillingService;
use App\Support\SectionFinancials;
use App\Support\TrainerFinancials;

/**
 * The two summary panels, followed through a term instead of read once.
 *
 * `FinancialSummariesTest` pins down what each figure means on a fixed set of
 * rows. This one is about what happens to those figures as the term moves: a
 * debt paid off late, a student who leaves still owing, a registration
 * cancelled and refunded, cycles billed one by one on a per-session course, and
 * the trainer being paid out of their wallet. Every one of those is a step the
 * desk actually takes, and each one moves a different pair of columns.
 *
 * Two invariants are checked after every step, because a panel that drifts is
 * worse than one that is plainly wrong — it gets believed:
 *
 *   section:  collected − trainer credited = net to the centre
 *   trainer:  credited − paid out          = the wallet balance on their page
 */

/** Both summaries still adding up, whatever the last step did to them. */
function assertSummariesAgree(Section $section, Trainer $trainer, string $stage): void
{
    $money = SectionFinancials::for($section->fresh());
    $wallet = TrainerFinancials::for($trainer->fresh());

    expect($money->net())
        ->toBe(round($money->collected - $money->trainerCredited, 2), "section net after {$stage}")
        ->and(round($wallet->credited - $wallet->paidOut, 2))
        ->toBe($wallet->balance, "trainer wallet after {$stage}")
        // Nothing collected can be owed, and nothing owed can be collected:
        // the two halves must still cover the whole bill between them.
        ->and(round($money->collected + $money->outstanding, 2))
        ->toBe($money->expected, "section bill after {$stage}");
}

/** A student on the roll, holding `$balance` before anything is charged. */
function scenarioStudent(float $balance = 0): Student
{
    $student = Student::create([
        'name' => ['en' => 'Scenario Student', 'ar' => 'طالب'],
        'username' => 'scen_s_'.uniqid(),
        'password' => 'password',
    ]);

    if ($balance > 0) {
        $student->depositFloat($balance, ['description' => 'Opening balance']);
    }

    return $student;
}

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Scenario Trainer', 'ar' => 'أستاذ'],
        'username' => 'scen_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->subject = Subject::create(['name' => ['en' => 'Scenario', 'ar' => 'مادة']]);
});

/** A fixed-price course: the whole fee is raised the day a student joins. */
function fixedCourse(Trainer $trainer, Subject $subject, float $price = 400): Section
{
    return Section::create([
        'name' => 'شعبة بسعر ثابت',
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => $price,
        'trainer_rate' => 50,
    ]);
}

it('follows a fixed-price section from enrolment to refund', function () {
    $section = fixedCourse($this->trainer, $this->subject);

    // ── Nobody enrolled yet ────────────────────────────────────────────────
    $money = SectionFinancials::for($section);
    expect($money->expected)->toBe(0.0)
        ->and($money->collectionRate())->toBe(0.0);

    // ── Three students join: one pays up front, one half, one nothing ──────
    $payer = scenarioStudent(400);
    $halfPayer = scenarioStudent(200);
    $debtor = scenarioStudent();

    $registrations = [];

    foreach ([$payer, $halfPayer, $debtor] as $student) {
        $registrations[$student->id] = Registration::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'amount_due' => 400,
            'amount_paid' => 400,
        ]);
    }

    $money = SectionFinancials::for($section);

    expect($money->expected)->toBe(1200.0)
        ->and($money->collected)->toBe(600.0)
        ->and($money->outstanding)->toBe(600.0)
        // Half the money in, so half the trainer's 600 share is credited.
        ->and($money->trainerShare)->toBe(600.0)
        ->and($money->trainerCredited)->toBe(300.0)
        ->and($money->collectionRate())->toBe(50.0);

    assertSummariesAgree($section, $this->trainer, 'enrolment');

    // ── The debtor comes back and settles in full ──────────────────────────
    $debtor->depositFloat(400, ['description' => 'Late payment']);
    PaymentAllocationService::apply($registrations[$debtor->id]->fresh(), 400);

    $money = SectionFinancials::for($section);

    expect($money->collected)->toBe(1000.0)
        ->and($money->outstanding)->toBe(200.0)
        // Their whole 200 share lands the moment the debt clears — once, not
        // twice: the 300 already credited is not re-credited.
        ->and($money->trainerCredited)->toBe(500.0);

    assertSummariesAgree($section, $this->trainer, 'late payment');

    // ── The half-payer leaves, still owing the other half ──────────────────
    SectionWithdrawalService::withdraw($registrations[$halfPayer->id]->fresh(), now(), 'moved away');

    $money = SectionFinancials::for($section);

    // Leaving settles nothing: the debt is still the centre's to collect, so
    // the row stays in the summary exactly as it stood.
    expect($money->expected)->toBe(1200.0)
        ->and($money->outstanding)->toBe(200.0);

    assertSummariesAgree($section, $this->trainer, 'withdrawal');

    // ── One registration is cancelled and refunded ─────────────────────────
    $registrations[$payer->id]->fresh()->deleteWithWalletAdjustments();

    $money = SectionFinancials::for($section);

    // Gone from the books entirely — charge, collection and trainer share.
    expect($money->expected)->toBe(800.0)
        ->and($money->collected)->toBe(600.0)
        ->and($money->trainerCredited)->toBe(300.0)
        // The money went back to the student.
        ->and(round((float) $payer->fresh()->balanceFloat, 2))->toBe(400.0);

    assertSummariesAgree($section, $this->trainer, 'cancellation');
});

it('grows what a per-session section expects as its cycles are billed', function () {
    $section = Section::create([
        'name' => 'شعبة بالحصة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'price' => 0,
        'cycle_fee' => 100,
        'sessions_per_cycle' => 4,
        'trainer_rate' => 50,
    ]);

    $student = scenarioStudent(300);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // One cycle billed and paid: the course has asked for 100 so far, not for
    // some imagined total of the whole term.
    $money = SectionFinancials::for($section);
    expect($money->expected)->toBe(100.0)
        ->and($money->collected)->toBe(100.0)
        ->and($money->outstanding)->toBe(0.0);

    assertSummariesAgree($section, $this->trainer, 'first cycle');

    // Two more cycles are collected as the term runs on.
    SessionBillingService::payCycle($registration->fresh(), 2);

    $money = SectionFinancials::for($section);

    expect($money->expected)->toBe(300.0)
        ->and($money->collected)->toBe(300.0)
        ->and($money->trainerShare)->toBe(150.0)
        ->and($money->trainerCredited)->toBe(150.0);

    assertSummariesAgree($section, $this->trainer, 'further cycles');

    // A fourth cycle is billed with nothing left on the wallet to back it.
    SessionBillingService::payCycle($registration->fresh(), 1);

    $money = SectionFinancials::for($section);

    expect($money->expected)->toBe(400.0)
        ->and($money->collected)->toBe(300.0)
        ->and($money->outstanding)->toBe(100.0)
        // The trainer's share of an unpaid cycle waits behind the debt.
        ->and($money->trainerShare)->toBe(200.0)
        ->and($money->trainerCredited)->toBe(150.0);

    $wallet = TrainerFinancials::for($this->trainer->fresh());
    expect($wallet->pending)->toBe(50.0)
        ->and($wallet->expectedTotal())->toBe(200.0);

    assertSummariesAgree($section, $this->trainer, 'unpaid cycle');
});

it('charges the whole roster as lessons are held, and keeps the panels true', function () {
    $section = Section::create([
        'name' => 'شعبة تلقائية',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'price' => 0,
        'cycle_fee' => 80,
        'sessions_per_cycle' => 2,
        'auto_charge_cycles' => true,
        'trainer_rate' => 50,
        'start_date' => now()->subMonth(),
    ]);

    foreach ([160, 80] as $balance) {
        Registration::create([
            'student_id' => scenarioStudent($balance)->id,
            'section_id' => $section->id,
            // Joined before the lessons below were held — a lesson from before
            // a student's enrolment date is the section's history, not their
            // bill, so a registration dated today would be charged for none of
            // them and the cycle would never close.
            'enrolled_at' => now()->subMonth()->toDateString(),
            'amount_due' => 80,
            'amount_paid' => 80,
        ]);
    }

    $money = SectionFinancials::for($section);
    expect($money->expected)->toBe(160.0)->and($money->collected)->toBe(160.0);

    // Two lessons held closes the first cycle for everyone and bills the next.
    foreach ([2, 1] as $daysAgo) {
        SectionSession::create([
            'section_id' => $section->id,
            'date' => now()->subDays($daysAgo)->toDateString(),
            'status' => SectionSession::STATUS_HELD,
            'counts_toward_billing' => true,
        ]);
    }

    $money = SectionFinancials::for($section);

    // Both were billed a second cycle; only the student who came with 160 on
    // the wallet could cover it.
    expect($money->expected)->toBe(320.0)
        ->and($money->collected)->toBe(240.0)
        ->and($money->outstanding)->toBe(80.0);

    assertSummariesAgree($section, $this->trainer, 'auto-charged cycle');
});

it('moves a trainer payout out of the balance without touching what they earned', function () {
    $section = fixedCourse($this->trainer, $this->subject);

    Registration::create([
        'student_id' => scenarioStudent(400)->id,
        'section_id' => $section->id,
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    $before = TrainerFinancials::for($this->trainer->fresh());
    expect($before->credited)->toBe(200.0)
        ->and($before->paidOut)->toBe(0.0)
        ->and($before->balance)->toBe(200.0);

    // The desk hands them 120 of it.
    $this->trainer->withdrawFloat(120, ['description' => 'Payout']);

    $after = TrainerFinancials::for($this->trainer->fresh());

    expect($after->credited)->toBe(200.0)
        ->and($after->paidOut)->toBe(120.0)
        ->and($after->balance)->toBe(80.0)
        // Paying a trainer is not the section earning less: the section's books
        // are about what students paid, not about what left the trainer's
        // wallet afterwards.
        ->and(SectionFinancials::for($section)->trainerCredited)->toBe(200.0);

    assertSummariesAgree($section, $this->trainer, 'payout');
});

it('keeps two sections of the same trainer apart in the section panel', function () {
    $first = fixedCourse($this->trainer, $this->subject, 400);
    $second = Section::create([
        'name' => 'شعبة ثانية',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 300,
        'trainer_rate' => 50,
    ]);

    Registration::create([
        'student_id' => scenarioStudent(400)->id,
        'section_id' => $first->id,
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    Registration::create([
        'student_id' => scenarioStudent()->id,
        'section_id' => $second->id,
        'amount_due' => 300,
        'amount_paid' => 300,
    ]);

    expect(SectionFinancials::for($first)->collected)->toBe(400.0)
        ->and(SectionFinancials::for($first)->outstanding)->toBe(0.0)
        ->and(SectionFinancials::for($second)->collected)->toBe(0.0)
        ->and(SectionFinancials::for($second)->outstanding)->toBe(300.0);

    // The trainer's own panel is the sum of both, because the wallet is one.
    $wallet = TrainerFinancials::for($this->trainer->fresh());

    expect($wallet->credited)->toBe(200.0)
        ->and($wallet->pending)->toBe(150.0)
        ->and($wallet->expectedTotal())->toBe(350.0);
});
