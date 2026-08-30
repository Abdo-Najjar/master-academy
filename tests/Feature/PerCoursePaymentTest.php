<?php

use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\FinancialDueService;

/**
 * A student sitting in two courses owes two separate bills against one wallet.
 * A plain deposit settles the oldest one first; this action has to settle the
 * course it was collected against, whatever the amount handed over.
 */
beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Multi Trainer', 'ar' => 'مدرب'],
        'username' => 'multi_trainer_'.uniqid(),
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
        'name' => ['en' => 'Multi Student', 'ar' => 'طالب'],
        'username' => 'multi_stu_'.uniqid(),
        'password' => 'password',
    ]);

    $this->paymentType = PaymentType::create(['name' => 'Cash '.uniqid()]);

    // Both charges land on an empty wallet, so both start fully unfunded. The
    // maths one is created first — it is what a FIFO deposit would settle.
    $this->maths = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->mathsSection->id,
        'payment_type_id' => $this->paymentType->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $this->arabic = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->arabicSection->id,
        'payment_type_id' => $this->paymentType->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);
});

it('credits the payment to the course it was collected against, not the oldest one', function () {
    CollectPaymentAction::collect($this->arabic, [
        'amount' => 100,
        'payment_type_id' => $this->paymentType->id,
    ]);

    expect(FinancialDueService::remainingBalance($this->arabic->fresh()))->toBe(0.0)
        ->and($this->arabic->fresh()->financial_status)->toBe('ok')
        // The older maths bill is untouched — it is a different subject.
        ->and(FinancialDueService::remainingBalance($this->maths->fresh()))->toBe(200.0)
        ->and($this->maths->fresh()->financial_status)->toBe('overdue');
});

it('accepts a partial amount and leaves the rest owed on that course', function () {
    CollectPaymentAction::collect($this->maths, [
        'amount' => 60,
        'payment_type_id' => $this->paymentType->id,
    ]);

    expect((float) $this->maths->fresh()->funded_amount)->toBe(60.0)
        ->and(FinancialDueService::remainingBalance($this->maths->fresh()))->toBe(140.0)
        ->and($this->maths->fresh()->financial_status)->toBe('due');
});

it('adds the money to the wallet and credits the trainer their share of it', function () {
    CollectPaymentAction::collect($this->arabic, [
        'amount' => 40,
        'payment_type_id' => $this->paymentType->id,
    ]);

    // 40 collected against a 100 charge, at a 50% trainer rate.
    expect((float) $this->arabic->fresh()->trainer_credited_amount)->toBe(20.0)
        // The deposit paid down the Arabic charge, so the wallet nets out where
        // it was: both charges were already withdrawn in full at creation.
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-260.0);
});

it('leaves anything paid beyond the course balance as wallet credit', function () {
    CollectPaymentAction::collect($this->arabic, [
        'amount' => 250,
        'payment_type_id' => $this->paymentType->id,
    ]);

    // Only the 100 this course owed was applied; the surplus does not leak
    // into the maths bill.
    expect((float) $this->arabic->fresh()->funded_amount)->toBe(100.0)
        ->and(FinancialDueService::remainingBalance($this->maths->fresh()))->toBe(200.0)
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-50.0);
});

it('ignores a zero or negative amount', function () {
    CollectPaymentAction::collect($this->maths, ['amount' => 0]);

    expect((float) $this->maths->fresh()->funded_amount)->toBe(0.0)
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-300.0);
});
