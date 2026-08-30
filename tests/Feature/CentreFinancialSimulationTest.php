<?php

use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Filament\Support\EnrollmentPayment;
use App\Models\Attendance;
use App\Models\ExemptionType;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\FinancialDueService;
use App\Services\PaymentAllocationService;
use App\Services\SessionBillingService;

/**
 * One term at one centre, followed from the first enrolment to the last refund,
 * with the books checked after every step.
 *
 * The other money tests each pin down one rule. This one is here for what only
 * shows up once the rules run together for weeks: three billing arrangements
 * side by side, students in more than one course, money arriving late, in
 * parts, and aimed at a particular subject, lessons held, apologised for and
 * paused, a withdrawal and a cancellation — all against two invariants that
 * must never bend:
 *
 *   student wallet  = everything handed over − everything charged + refunds
 *   trainer wallet  = the shares credited on their own live registrations
 *
 * If a step invents or loses a shekel, `assertBooksBalance()` catches it on the
 * next line rather than three screens later.
 */

/**
 * @param  array<int, float>  $deposited  what each family has handed over so far
 */
function assertBooksBalance(array $deposited, string $stage): void
{
    foreach (Student::withTrashed()->get() as $student) {
        // A cancelled registration keeps its charge on the row, so it is
        // counted and then given back — that is what the refund did.
        $charged = round((float) Registration::withTrashed()
            ->where('student_id', $student->id)->sum('amount_paid'), 2);

        $refunded = round((float) Registration::onlyTrashed()
            ->where('student_id', $student->id)->sum('amount_paid'), 2);

        expect(round((float) $student->fresh()->balanceFloat, 2))
            ->toBe(round(($deposited[$student->id] ?? 0.0) - $charged + $refunded, 2),
                "wallet of student #{$student->id} after {$stage}");
    }

    foreach (Trainer::withTrashed()->get() as $trainer) {
        // Cancelling claws the trainer's share back, so only live registrations
        // are still standing behind their balance.
        $credited = round((float) Registration::query()
            ->whereHas('section', fn ($q) => $q->withTrashed()->where('trainer_id', $trainer->id))
            ->sum('trainer_credited_amount'), 2);

        expect(round((float) $trainer->fresh()->balanceFloat, 2))
            ->toBe($credited, "wallet of trainer #{$trainer->id} after {$stage}");
    }

    foreach (Registration::withTrashed()->with('section')->get() as $registration) {
        expect((float) $registration->funded_amount)
            ->toBeLessThanOrEqual((float) $registration->amount_paid + 0.009,
                "registration #{$registration->id} funded beyond its charge after {$stage}");

        // The trainer is on a rate, not a flat sum: their credit has to track
        // the funded portion of the charge however often either one moves.
        $rate = (float) $registration->amount_paid > 0
            ? (float) $registration->trainer_amount / (float) $registration->amount_paid
            : 0.0;

        expect(round((float) $registration->trainer_credited_amount, 2))
            ->toBe(round((float) $registration->funded_amount * $rate, 2),
                "trainer share of registration #{$registration->id} after {$stage}");
    }
}

/** Hold a lesson and mark the given students present, absent or excused. */
function holdLesson(Section $section, string $date, array $attendance = []): void
{
    SectionSession::create([
        'section_id' => $section->id,
        'date' => $date,
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
    ]);

    if ($attendance !== []) {
        Attendance::recordDay($section->id, $date, $attendance);
    }
}

beforeEach(function () {
    $this->cash = PaymentType::create(['name' => 'نقداً '.uniqid()]);

    $this->trainers = [
        'huda' => Trainer::create(['name' => ['ar' => 'أ. هدى', 'en' => 'Huda'], 'username' => 'sim_huda_'.uniqid(), 'password' => 'password', 'default_rate' => 50]),
        'kareem' => Trainer::create(['name' => ['ar' => 'أ. كريم', 'en' => 'Kareem'], 'username' => 'sim_kareem_'.uniqid(), 'password' => 'password', 'default_rate' => 40]),
    ];

    $this->subjects = [
        'maths' => Subject::create(['name' => ['ar' => 'الرياضيات', 'en' => 'Maths']]),
        'english' => Subject::create(['name' => ['ar' => 'الإنجليزي', 'en' => 'English']]),
        'science' => Subject::create(['name' => ['ar' => 'العلوم', 'en' => 'Science']]),
    ];

    $this->sections = [
        // One price for the term, paid up front. Huda takes 50%.
        'maths' => Section::create([
            'name' => 'رياضيات ١',
            'subject_id' => $this->subjects['maths']->id,
            'trainer_id' => $this->trainers['huda']->id,
            'price' => 400,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'capacity' => 10,
        ]),
        // 60 ₪ every 4 lessons, billed by the system as each cycle closes.
        'english' => Section::create([
            'name' => 'إنجليزي ١',
            'subject_id' => $this->subjects['english']->id,
            'trainer_id' => $this->trainers['kareem']->id,
            'price' => 0,
            'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
            'sessions_per_cycle' => 4,
            'cycle_fee' => 60,
            'auto_charge_cycles' => true,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'capacity' => 10,
        ]),
        // 50 ₪ every 2 lessons, collected at the desk.
        'science' => Section::create([
            'name' => 'علوم ١',
            'subject_id' => $this->subjects['science']->id,
            'trainer_id' => $this->trainers['huda']->id,
            'price' => 0,
            'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
            'sessions_per_cycle' => 2,
            'cycle_fee' => 50,
            'auto_charge_cycles' => false,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'capacity' => 10,
        ]),
    ];

    $this->students = [
        'layla' => Student::create(['name' => ['ar' => 'ليلى', 'en' => 'Layla'], 'username' => 'sim_layla_'.uniqid(), 'password' => 'password', 'status' => 'active']),
        'omar' => Student::create(['name' => ['ar' => 'عمر', 'en' => 'Omar'], 'username' => 'sim_omar_'.uniqid(), 'password' => 'password', 'status' => 'active']),
        'noor' => Student::create(['name' => ['ar' => 'نور', 'en' => 'Noor'], 'username' => 'sim_noor_'.uniqid(), 'password' => 'password', 'status' => 'active']),
    ];

    $this->deposited = [
        $this->students['layla']->id => 0.0,
        $this->students['omar']->id => 0.0,
        $this->students['noor']->id => 0.0,
    ];
});

it('runs a whole term of money without losing or inventing a shekel', function () {
    $layla = $this->students['layla'];
    $omar = $this->students['omar'];
    $noor = $this->students['noor'];
    $huda = $this->trainers['huda'];
    $kareem = $this->trainers['kareem'];

    // ---------------------------------------------------------------- day one
    // Layla's family pays the maths term in full at the desk. The money is
    // banked before the charge, which is what makes the bill settled on the
    // spot instead of a wallet that dips negative and climbs back.
    EnrollmentPayment::collect(['payment_amount' => 400, 'payment_type_id' => $this->cash->id], $layla->id);
    $this->deposited[$layla->id] += 400;

    $laylaMaths = Registration::create([
        'student_id' => $layla->id,
        'section_id' => $this->sections['maths']->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    expect($laylaMaths->fresh()->financial_status)->toBe('ok')
        ->and((float) $laylaMaths->fresh()->funded_amount)->toBe(400.0)
        ->and((float) $huda->fresh()->balanceFloat)->toBe(200.0)
        ->and((float) $layla->fresh()->balanceFloat)->toBe(0.0);

    assertBooksBalance($this->deposited, 'Layla paid up front');

    // Omar takes the same course and pays nothing yet: charged in full, the
    // trainer credited nothing, and the badge says so.
    $omarMaths = Registration::create([
        'student_id' => $omar->id,
        'section_id' => $this->sections['maths']->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    expect($omarMaths->fresh()->financial_status)->toBe('overdue')
        ->and((float) $omarMaths->fresh()->trainer_credited_amount)->toBe(0.0)
        ->and((float) $omar->fresh()->balanceFloat)->toBe(-400.0)
        // Huda's wallet did not move for a course nobody paid for.
        ->and((float) $huda->fresh()->balanceFloat)->toBe(200.0);

    assertBooksBalance($this->deposited, 'Omar enrolled unpaid');

    // He also takes English, billed by the lesson, nothing up front.
    $omarEnglish = Registration::create([
        'student_id' => $omar->id,
        'section_id' => $this->sections['english']->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    // Noor takes maths on a 100 ₪ exemption and pays what is left of it.
    $scholarship = ExemptionType::create(['name' => 'منحة جزئية '.uniqid()]);

    EnrollmentPayment::collect(['payment_amount' => 300, 'payment_type_id' => $this->cash->id], $noor->id);
    $this->deposited[$noor->id] += 300;

    $noorMaths = Registration::create([
        'student_id' => $noor->id,
        'section_id' => $this->sections['maths']->id,
        'enrolled_at' => '2026-09-01',
        'exemption_type_id' => $scholarship->id,
        'exemption_amount' => 100,
        'amount_due' => 400,
        'amount_paid' => 300,
    ]);

    expect($noorMaths->fresh()->financial_status)->toBe('ok')
        ->and((float) $noorMaths->fresh()->funded_amount)->toBe(300.0)
        // Huda is paid her rate on what was collected, not on the list price.
        ->and((float) $huda->fresh()->balanceFloat)->toBe(350.0)
        ->and((float) $noor->fresh()->balanceFloat)->toBe(0.0);

    assertBooksBalance($this->deposited, 'Noor enrolled with an exemption');

    // And Science, one cycle paid up front.
    EnrollmentPayment::collect(['payment_amount' => 50, 'payment_type_id' => $this->cash->id], $noor->id);
    $this->deposited[$noor->id] += 50;

    $noorScience = Registration::create([
        'student_id' => $noor->id,
        'section_id' => $this->sections['science']->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 50,
        'amount_paid' => 50,
    ]);

    // 50 ₪ bought exactly one cycle of two lessons.
    expect($noorScience->fresh()->paid_through_session)->toBe(2)
        ->and((float) $huda->fresh()->balanceFloat)->toBe(375.0);

    assertBooksBalance($this->deposited, 'Noor enrolled in science');

    // ------------------------------------------------------ the first lessons
    // Four English lessons. Omar bought nothing up front, so the first lesson
    // already closes a cycle and the fourth closes the next: two cycles billed,
    // eight lessons bought, four of them used.
    foreach (['2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05'] as $date) {
        holdLesson($this->sections['english'], $date, [$omar->id => 'present']);
    }

    $stored = $omarEnglish->fresh();

    expect($stored->sessions_counted)->toBe(4)
        ->and($stored->paid_through_session)->toBe(8)
        ->and((float) $stored->amount_paid)->toBe(120.0)
        ->and((float) $stored->funded_amount)->toBe(0.0)
        // Four lessons still in hand, but 120 ₪ was billed and never handed
        // over. Reading the counter alone would call this "paid".
        ->and($stored->financial_status)->toBe('overdue')
        ->and((float) $omar->fresh()->balanceFloat)->toBe(-520.0)
        ->and((float) $kareem->fresh()->balanceFloat)->toBe(0.0);

    assertBooksBalance($this->deposited, 'English charged its own cycles');

    // Two Science lessons use up the cycle Noor paid for. This section waits to
    // be collected, so nothing is billed — the counter is what flags her.
    foreach (['2026-09-02', '2026-09-03'] as $date) {
        holdLesson($this->sections['science'], $date, [$noor->id => 'present']);
    }

    $stored = $noorScience->fresh();

    expect($stored->sessions_counted)->toBe(2)
        ->and($stored->paid_through_session)->toBe(2)
        ->and((float) $stored->amount_paid)->toBe(50.0)
        ->and($stored->financial_status)->toBe('due')
        // Nothing was charged, so no money moved.
        ->and((float) $noor->fresh()->balanceFloat)->toBe(0.0);

    assertBooksBalance($this->deposited, 'Science used up its cycle');

    // --------------------------------------------------- money arrives, aimed
    // Omar's family brings 200 and says what it is for: 140 on maths, 60 on
    // English. Neither amount may leak into the other.
    WalletActions::handleDeposit($omar->fresh(), [
        'amount' => 200,
        'payment_type_id' => $this->cash->id,
        'allocations' => [$omarMaths->id => 140, $omarEnglish->id => 60],
    ]);
    $this->deposited[$omar->id] += 200;

    expect((float) $omarMaths->fresh()->funded_amount)->toBe(140.0)
        ->and($omarMaths->fresh()->financial_status)->toBe('due')
        ->and((float) $omarEnglish->fresh()->funded_amount)->toBe(60.0)
        // One cycle of the two billed is still owed, so it is not "paid" yet.
        ->and($omarEnglish->fresh()->financial_status)->toBe('due')
        // Huda took half of the 140, Kareem 40% of the 60.
        ->and((float) $huda->fresh()->balanceFloat)->toBe(445.0)
        ->and((float) $kareem->fresh()->balanceFloat)->toBe(24.0)
        ->and((float) $omar->fresh()->balanceFloat)->toBe(-320.0);

    assertBooksBalance($this->deposited, 'Omar paid two courses at once');

    // Noor pays 50 without naming a course. Only Science owes, so it goes there
    // — and because Science is collected by hand, the money has to close the
    // cycle it paid for rather than sit as credit behind an unpaid badge.
    WalletActions::handleDeposit($noor->fresh(), [
        'amount' => 50,
        'payment_type_id' => $this->cash->id,
    ]);
    $this->deposited[$noor->id] += 50;

    $stored = $noorScience->fresh();

    expect((float) $stored->amount_paid)->toBe(100.0)
        ->and((float) $stored->funded_amount)->toBe(100.0)
        ->and($stored->paid_through_session)->toBe(4)
        ->and($stored->financial_status)->toBe('warning')
        ->and(PaymentAllocationService::amountDueNow($stored))->toBe(0.0)
        ->and((float) $huda->fresh()->balanceFloat)->toBe(470.0);

    assertBooksBalance($this->deposited, 'Noor paid without naming a course');

    // ----------------------------------------------------- absence and breaks
    // Omar apologises for one English lesson and attends another. Only the
    // attended one is his to pay for.
    holdLesson($this->sections['english'], '2026-09-08', [$omar->id => 'excused']);
    holdLesson($this->sections['english'], '2026-09-09', [$omar->id => 'present']);

    expect($omarEnglish->fresh()->sessions_counted)->toBe(5);

    // He then takes a fortnight off. Lessons held inside it are not charged.
    SessionBillingService::pause($omarEnglish->fresh(), '2026-09-10');

    foreach (['2026-09-11', '2026-09-12', '2026-09-15'] as $date) {
        holdLesson($this->sections['english'], $date, [$omar->id => 'present']);
    }

    expect($omarEnglish->fresh()->sessions_counted)->toBe(5, 'lessons during a break are not charged');

    SessionBillingService::resume($omarEnglish->fresh(), '2026-09-16');
    holdLesson($this->sections['english'], '2026-09-17', [$omar->id => 'present']);

    expect($omarEnglish->fresh()->sessions_counted)->toBe(6)
        // Still inside the eight lessons already billed: no new charge.
        ->and((float) $omarEnglish->fresh()->amount_paid)->toBe(120.0);

    assertBooksBalance($this->deposited, 'a break and an apology');

    // -------------------------------------------------------- paying the rest
    // The maths balance is settled from the registration itself.
    CollectPaymentAction::collect($omarMaths->fresh(), [
        'amount' => 260,
        'payment_type_id' => $this->cash->id,
    ]);
    $this->deposited[$omar->id] += 260;

    expect(FinancialDueService::remainingBalance($omarMaths->fresh()))->toBe(0.0)
        ->and($omarMaths->fresh()->financial_status)->toBe('ok')
        ->and((float) $huda->fresh()->balanceFloat)->toBe(600.0)
        // English is untouched by a payment aimed at maths.
        ->and((float) $omarEnglish->fresh()->funded_amount)->toBe(60.0);

    assertBooksBalance($this->deposited, 'Omar settled maths');

    // ---------------------------------------------------------- leaving early
    // Layla withdraws. Leaving moves no money on its own — what was paid stays
    // paid, and a refund is a separate decision.
    $laylaMaths->fresh()->update(['left_at' => '2026-10-01', 'leave_reason' => 'انتقلت مدرسة']);

    expect((float) $layla->fresh()->balanceFloat)->toBe(0.0)
        ->and((float) $huda->fresh()->balanceFloat)->toBe(600.0)
        ->and($laylaMaths->fresh()->hasLeft())->toBeTrue();

    assertBooksBalance($this->deposited, 'Layla withdrew');

    // Noor's Science registration is cancelled outright: the money goes back to
    // her and Huda's share is clawed back to the shekel.
    $noorScience->fresh()->deleteWithWalletAdjustments();

    expect((float) $noor->fresh()->balanceFloat)->toBe(100.0, 'everything Noor paid for science came back')
        ->and((float) $huda->fresh()->balanceFloat)->toBe(550.0);

    assertBooksBalance($this->deposited, 'Noor was refunded');

    // ------------------------------------------------------------- the ledger
    // One cycle of Omar's English is still owed, and nothing else is.
    expect(FinancialDueService::outstandingAmount())->toBe(60.0);

    $handedOver = round(array_sum($this->deposited), 2);
    $studentWallets = round(Student::withTrashed()->get()->sum(fn (Student $s) => (float) $s->balanceFloat), 2);
    $trainerWallets = round(Trainer::withTrashed()->get()->sum(fn (Trainer $t) => (float) $t->balanceFloat), 2);
    $liveCharges = round((float) Registration::query()->sum('amount_paid'), 2);

    expect($handedOver)->toBe(1260.0)
        // Layla 0, Omar −60 still owing, Noor +100 refunded and unspent.
        ->and($studentWallets)->toBe(40.0)
        ->and($liveCharges)->toBe(1220.0)
        // The books close: what came in, minus what is still on the wallets, is
        // exactly what the live registrations were charged.
        ->and(round($handedOver - $studentWallets, 2))->toBe($liveCharges)
        ->and($trainerWallets)->toBe(574.0);
});

it('never funds a course past its own bill, whatever order money arrives in', function () {
    $omar = $this->students['omar'];

    $registration = Registration::create([
        'student_id' => $omar->id,
        'section_id' => $this->sections['maths']->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    // Paid in dribs and drabs, down to the agora, then handed a sum far past
    // what is left owing.
    foreach ([50, 125, 3.33, 221.67, 500] as $amount) {
        $due = PaymentAllocationService::amountDueNow($registration->fresh());

        WalletActions::handleDeposit($omar->fresh(), [
            'amount' => $amount,
            'allocations' => [$registration->id => min($amount, $due)],
        ]);
        $this->deposited[$omar->id] += $amount;

        expect((float) $registration->fresh()->funded_amount)
            ->toBeLessThanOrEqual(400.009, "funded past the bill after a {$amount} payment");
    }

    expect((float) $registration->fresh()->funded_amount)->toBe(400.0)
        ->and($registration->fresh()->financial_status)->toBe('ok')
        // The surplus is credit on the wallet, not an extra share for anybody.
        ->and((float) $omar->fresh()->balanceFloat)->toBe(500.0)
        ->and((float) $this->trainers['huda']->fresh()->balanceFloat)->toBe(200.0);

    assertBooksBalance($this->deposited, 'a run of odd payments');
});

it('keeps every course of a three-course student separate', function () {
    $omar = $this->students['omar'];

    $maths = Registration::create(['student_id' => $omar->id, 'section_id' => $this->sections['maths']->id, 'enrolled_at' => '2026-09-01', 'amount_due' => 400, 'amount_paid' => 400]);
    $english = Registration::create(['student_id' => $omar->id, 'section_id' => $this->sections['english']->id, 'enrolled_at' => '2026-09-01', 'amount_due' => 60, 'amount_paid' => 60]);
    $science = Registration::create(['student_id' => $omar->id, 'section_id' => $this->sections['science']->id, 'enrolled_at' => '2026-09-01', 'amount_due' => 50, 'amount_paid' => 50]);

    // All three owe, and the deposit screen has to say so course by course.
    $dues = PaymentAllocationService::outstandingFor($omar->fresh());

    expect($dues->pluck('id')->all())->toBe([$maths->id, $english->id, $science->id])
        ->and($dues->map(fn ($r) => PaymentAllocationService::amountDueNow($r))->all())->toBe([400.0, 60.0, 50.0]);

    // Pay the middle one only.
    WalletActions::handleDeposit($omar->fresh(), [
        'amount' => 60,
        'allocations' => [$english->id => 60],
    ]);
    $this->deposited[$omar->id] += 60;

    expect((float) $english->fresh()->funded_amount)->toBe(60.0)
        ->and((float) $maths->fresh()->funded_amount)->toBe(0.0)
        ->and((float) $science->fresh()->funded_amount)->toBe(0.0)
        // Only Kareem was credited: the other two courses are Huda's, and
        // neither of them was paid.
        ->and((float) $this->trainers['kareem']->fresh()->balanceFloat)->toBe(24.0)
        ->and((float) $this->trainers['huda']->fresh()->balanceFloat)->toBe(0.0);

    assertBooksBalance($this->deposited, 'one course of three paid');
});
