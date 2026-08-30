<?php

use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Models\Attendance;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\FinancialDueService;
use App\Services\PaymentAllocationService;
use Filament\Support\Exceptions\Halt;

/**
 * A deposit lands on one wallet but the bills are per course, so the desk has to
 * be able to say which course the money is for — "50 for maths, 50 for arabic" —
 * instead of letting it fall on the oldest bill by default.
 */
beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Alloc Trainer', 'ar' => 'مدرب'],
        'username' => 'alloc_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $maths = Subject::create(['name' => ['en' => 'Maths', 'ar' => 'رياضيات']]);
    $arabic = Subject::create(['name' => ['en' => 'Arabic', 'ar' => 'عربي']]);

    $this->mathsSection = Section::create([
        'name' => 'Maths Section',
        'subject_id' => $maths->id,
        'trainer_id' => $this->trainer->id,
        'price' => 200,
    ]);

    $this->arabicSection = Section::create([
        'name' => 'Arabic Section',
        'subject_id' => $arabic->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
    ]);

    $this->student = Student::create([
        'name' => ['en' => 'Alloc Student', 'ar' => 'طالب'],
        'username' => 'alloc_stu_'.uniqid(),
        'password' => 'password',
    ]);

    $this->paymentType = PaymentType::create(['name' => 'Cash '.uniqid()]);

    // Both charges land on an empty wallet, so both start fully unfunded. Maths
    // is the older bill — the one a FIFO deposit would have settled.
    $this->maths = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->mathsSection->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $this->arabic = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->arabicSection->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);
});

it('lists every course that owes money, with what each one owes', function () {
    $dues = PaymentAllocationService::outstandingFor($this->student);

    expect($dues->pluck('id')->all())->toBe([$this->maths->id, $this->arabic->id])
        ->and(PaymentAllocationService::amountDueNow($dues[0]))->toBe(200.0)
        ->and(PaymentAllocationService::amountDueNow($dues[1]))->toBe(100.0);
});

it('sends the deposit to the courses it was assigned to, not the oldest one', function () {
    WalletActions::handleDeposit($this->student, [
        'amount' => 100,
        'payment_type_id' => $this->paymentType->id,
        'allocations' => [$this->arabic->id => 100],
    ]);

    expect(FinancialDueService::remainingBalance($this->arabic->fresh()))->toBe(0.0)
        ->and($this->arabic->fresh()->financial_status)->toBe('ok')
        // The older maths bill is a different subject and stays untouched.
        ->and(FinancialDueService::remainingBalance($this->maths->fresh()))->toBe(200.0)
        ->and($this->maths->fresh()->financial_status)->toBe('overdue');
});

it('splits one deposit across several courses', function () {
    WalletActions::handleDeposit($this->student, [
        'amount' => 150,
        'allocations' => [$this->maths->id => 50, $this->arabic->id => 100],
    ]);

    expect((float) $this->maths->fresh()->funded_amount)->toBe(50.0)
        ->and($this->maths->fresh()->financial_status)->toBe('due')
        ->and((float) $this->arabic->fresh()->funded_amount)->toBe(100.0)
        ->and($this->arabic->fresh()->financial_status)->toBe('ok')
        // 300 was charged at registration, 150 has now come back.
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-150.0);
});

it('credits the trainer only their share of what each course actually took', function () {
    WalletActions::handleDeposit($this->student, [
        'amount' => 150,
        'allocations' => [$this->maths->id => 50, $this->arabic->id => 100],
    ]);

    // 50% of 50 on maths, 50% of 100 on arabic.
    expect((float) $this->maths->fresh()->trainer_credited_amount)->toBe(25.0)
        ->and((float) $this->arabic->fresh()->trainer_credited_amount)->toBe(50.0);
});

it('leaves whatever was not assigned on the wallet as credit', function () {
    WalletActions::handleDeposit($this->student, [
        'amount' => 300,
        'allocations' => [$this->arabic->id => 100],
    ]);

    // Only the arabic bill was settled; the other 200 sits as credit rather
    // than leaking into the maths bill.
    expect((float) $this->arabic->fresh()->funded_amount)->toBe(100.0)
        ->and((float) $this->maths->fresh()->funded_amount)->toBe(0.0)
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(0.0);
});

it('still settles oldest-first when nothing was assigned', function () {
    WalletActions::handleDeposit($this->student, ['amount' => 250]);

    expect((float) $this->maths->fresh()->funded_amount)->toBe(200.0)
        ->and((float) $this->arabic->fresh()->funded_amount)->toBe(50.0);
});

it('treats course lines left blank as nothing assigned', function () {
    // What the form actually submits when the desk fills in the amount and
    // leaves the per-course boxes alone.
    WalletActions::handleDeposit($this->student, [
        'amount' => 250,
        'allocations' => [$this->maths->id => null, $this->arabic->id => ''],
    ]);

    expect((float) $this->maths->fresh()->funded_amount)->toBe(200.0)
        ->and((float) $this->arabic->fresh()->funded_amount)->toBe(50.0);
});

it('refuses to assign more than was handed over', function () {
    expect(fn () => WalletActions::handleDeposit($this->student, [
        'amount' => 100,
        'allocations' => [$this->maths->id => 80, $this->arabic->id => 80],
    ]))->toThrow(Halt::class);

    // Nothing was written: the money never reached the wallet either.
    expect((float) $this->maths->fresh()->funded_amount)->toBe(0.0)
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-300.0);
});

it('ignores an allocation aimed at another student\'s registration', function () {
    $other = Student::create([
        'name' => ['en' => 'Other', 'ar' => 'آخر'],
        'username' => 'alloc_other_'.uniqid(),
        'password' => 'password',
    ]);

    $foreign = Registration::create([
        'student_id' => $other->id,
        'section_id' => $this->mathsSection->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    WalletActions::handleDeposit($this->student, [
        'amount' => 100,
        'allocations' => [$foreign->id => 100],
    ]);

    expect((float) $foreign->fresh()->funded_amount)->toBe(0.0)
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-200.0);
});

/**
 * The reported bug: on a per-session course collected by hand, the status is
 * driven by the paid-through horizon, and recording money alone never moved it —
 * so a student who had paid kept reading "overdue" forever.
 */
it('closes the used-up cycles of a hand-collected per-session course', function () {
    $section = Section::create([
        'name' => 'Per Session Section',
        'subject_id' => $this->mathsSection->subject_id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 1,
        'cycle_fee' => 50,
        'auto_charge_cycles' => false,
        'start_date' => '2026-09-01',
    ]);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 50,
        'amount_paid' => 50,
    ]);

    // One cycle bought up front, then three more lessons held and unpaid.
    expect($registration->fresh()->paid_through_session)->toBe(1);

    foreach (['2026-09-02', '2026-09-03', '2026-09-04'] as $date) {
        SectionSession::create([
            'section_id' => $section->id,
            'date' => $date,
            'status' => SectionSession::STATUS_HELD,
        ]);
        Attendance::recordDay($section->id, $date, [$this->student->id => 'present']);
    }

    $stored = $registration->fresh();

    expect($stored->sessions_counted)->toBe(3)
        ->and($stored->paid_through_session)->toBe(1)
        ->and($stored->financial_status)->toBe('overdue')
        // 50 ₪ of the original charge is still unfunded, and three cycles are
        // waiting to be billed — the two lessons past the horizon plus the one
        // the student is about to sit, exactly what auto-charging would take.
        ->and(PaymentAllocationService::amountDueNow($stored))->toBe(200.0);

    WalletActions::handleDeposit($this->student, [
        'amount' => 200,
        'allocations' => [$registration->id => 200],
    ]);

    $settled = $registration->fresh();

    // The horizon moved past the counter, which is the only thing that clears
    // "overdue" on a per-session course.
    expect($settled->paid_through_session)->toBe(4)
        ->and((float) $settled->amount_paid)->toBe(200.0)
        ->and((float) $settled->funded_amount)->toBe(200.0)
        ->and($settled->financial_status)->toBe('warning')
        ->and(PaymentAllocationService::amountDueNow($settled))->toBe(0.0);
});

/**
 * Auto-charging normally keeps the horizon ahead of the counter on its own, so
 * there is nothing to collect. It is when that stops being true — charging
 * switched on after a backlog had already built up — that the course sticks on
 * "overdue" with no bill anywhere to pay it off. Collecting has to close those
 * cycles too, or the status never moves however much the student hands over.
 */
it('closes a backlog on a section that charges its own cycles', function () {
    $section = Section::create([
        'name' => 'Auto Section',
        'subject_id' => $this->mathsSection->subject_id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 1,
        'cycle_fee' => 50,
        'auto_charge_cycles' => false,
        'start_date' => '2026-09-01',
    ]);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 50,
        'amount_paid' => 50,
    ]);

    foreach (['2026-09-02', '2026-09-03'] as $date) {
        SectionSession::create([
            'section_id' => $section->id,
            'date' => $date,
            'status' => SectionSession::STATUS_HELD,
        ]);
        Attendance::recordDay($section->id, $date, [$this->student->id => 'present']);
    }

    // The backlog is already there when automatic charging is switched on.
    $section->update(['auto_charge_cycles' => true]);

    $stored = $registration->fresh();

    expect($stored->sessions_counted)->toBe(2)
        ->and($stored->paid_through_session)->toBe(1)
        ->and($stored->financial_status)->toBe('due');

    WalletActions::handleDeposit($this->student, [
        'amount' => 150,
        'allocations' => [$registration->id => 150],
    ]);

    $settled = $registration->fresh();

    expect($settled->paid_through_session)->toBe(3)
        ->and((float) $settled->funded_amount)->toBe(150.0)
        ->and($settled->financial_status)->toBe('warning')
        ->and(PaymentAllocationService::amountDueNow($settled))->toBe(0.0);
});

/**
 * Leaving the per-course boxes blank must settle the same debt that filling them
 * in would. The automatic path used to only move funded amounts, so on a
 * per-session course whose cycles were never billed it found nothing to settle
 * and the money sat as credit against a course still reading "overdue".
 */
it('closes per-session cycles even when no course was named', function () {
    $section = Section::create([
        'name' => 'Unnamed Section',
        'subject_id' => $this->mathsSection->subject_id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 1,
        'cycle_fee' => 60,
        'auto_charge_cycles' => false,
        'start_date' => '2026-09-01',
    ]);

    // Nothing charged up front, so there is no unfunded bill to find — only
    // unbilled cycles.
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    SectionSession::create([
        'section_id' => $section->id,
        'date' => '2026-09-02',
        'status' => SectionSession::STATUS_HELD,
    ]);
    Attendance::recordDay($section->id, '2026-09-02', [$this->student->id => 'present']);

    expect($registration->fresh()->financial_status)->toBe('due')
        ->and(PaymentAllocationService::amountDueNow($registration->fresh()))->toBe(120.0);

    // The two older fixed-course bills come first, then this one.
    WalletActions::handleDeposit($this->student, ['amount' => 420]);

    $settled = $registration->fresh();

    expect((float) $settled->amount_paid)->toBe(120.0)
        ->and((float) $settled->funded_amount)->toBe(120.0)
        ->and($settled->paid_through_session)->toBe(2)
        ->and($settled->financial_status)->toBe('warning');
});

it('buys only the whole cycles a partial payment covers', function () {
    $section = Section::create([
        'name' => 'Partial Section',
        'subject_id' => $this->mathsSection->subject_id,
        'trainer_id' => $this->trainer->id,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 2,
        'cycle_fee' => 60,
        'auto_charge_cycles' => false,
        'start_date' => '2026-09-01',
    ]);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    foreach (['2026-09-02', '2026-09-03'] as $date) {
        SectionSession::create([
            'section_id' => $section->id,
            'date' => $date,
            'status' => SectionSession::STATUS_HELD,
        ]);
        Attendance::recordDay($section->id, $date, [$this->student->id => 'present']);
    }

    // Two lessons used against nothing paid: the cycle just consumed plus the
    // one starting now, 120 ₪ to collect.
    expect(PaymentAllocationService::amountDueNow($registration->fresh()))->toBe(120.0);

    // 40 does not buy a cycle, so the horizon stays put and the money waits.
    WalletActions::handleDeposit($this->student, [
        'amount' => 40,
        'allocations' => [$registration->id => 40],
    ]);

    expect($registration->fresh()->paid_through_session)->toBe(0)
        ->and((float) $registration->fresh()->amount_paid)->toBe(0.0);
});
