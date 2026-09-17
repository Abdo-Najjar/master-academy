<?php

use App\Models\Branch;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Support\SectionFinancials;
use App\Support\TrainerFinancials;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Summary Trainer', 'ar' => 'أستاذ'],
        'username' => 'sum_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->subject = Subject::create(['name' => ['en' => 'Maths', 'ar' => 'رياضيات']]);

    $this->section = Section::create([
        'name' => 'Summary Section',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 200,
        'trainer_rate' => 40,
    ]);
});

/** A student holding `$balance` before anything is charged to them. */
function summaryStudent(float $balance = 0): Student
{
    $student = Student::create([
        'name' => ['en' => 'Summary Student', 'ar' => 'طالب'],
        'username' => 'sum_stu_'.uniqid(),
        'password' => 'password',
    ]);

    if ($balance > 0) {
        $student->depositFloat($balance, ['description' => 'Opening balance']);
    }

    return $student;
}

it('sums what a section is owed, what it collected and what is left', function () {
    // Paid in full up front: the whole charge is funded.
    Registration::create([
        'student_id' => summaryStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    // Half down: 100 funded, 100 still owed.
    Registration::create([
        'student_id' => summaryStudent(100)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    // Exempted by 50 and paying nothing: the charge is the discounted 150, and
    // every shekel of it is outstanding.
    Registration::create([
        'student_id' => summaryStudent()->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'exemption_amount' => 50,
        'amount_paid' => 150,
    ]);

    $totals = SectionFinancials::for($this->section);

    expect($totals->registrations)->toBe(3)
        // Net charges, not list price: the exempted student counts at 150.
        ->and($totals->expected)->toBe(550.0)
        ->and($totals->collected)->toBe(300.0)
        ->and($totals->outstanding)->toBe(250.0)
        ->and($totals->exemptions)->toBe(50.0)
        // 40% of the 550 charged, of which only the funded 300 was credited.
        ->and($totals->trainerShare)->toBe(220.0)
        ->and($totals->trainerCredited)->toBe(120.0)
        ->and($totals->net())->toBe(180.0);
});

it('reports a section with no registrations as all zeroes rather than failing', function () {
    $totals = SectionFinancials::for($this->section);

    expect($totals->registrations)->toBe(0)
        ->and($totals->expected)->toBe(0.0)
        ->and($totals->collected)->toBe(0.0)
        ->and($totals->outstanding)->toBe(0.0)
        ->and($totals->collectionRate())->toBe(0.0);
});

it('leaves a deleted registration out of the section summary', function () {
    $registration = Registration::create([
        'student_id' => summaryStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    expect(SectionFinancials::for($this->section)->expected)->toBe(200.0);

    $registration->delete();

    expect(SectionFinancials::for($this->section)->expected)->toBe(0.0);
});

it('reports the collection rate as a percentage of what was billed', function () {
    Registration::create([
        'student_id' => summaryStudent(50)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    expect(SectionFinancials::for($this->section)->collectionRate())->toBe(25.0);
});

it('splits a trainer wallet into what was credited, paid out and still owed', function () {
    // A fully funded registration credits the trainer their 40%.
    Registration::create([
        'student_id' => summaryStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    // The desk hands them 50 of it.
    $this->trainer->withdrawFloat(50, ['description' => 'Payout']);

    $totals = TrainerFinancials::for($this->trainer->fresh());

    expect($totals->credited)->toBe(80.0)
        ->and($totals->paidOut)->toBe(50.0)
        ->and($totals->balance)->toBe(30.0)
        ->and($totals->pending)->toBe(0.0);
});

it('counts a share the students have not paid for as awaiting collection', function () {
    // Nothing in the wallet, so the whole charge goes unfunded and the
    // trainer's 40% waits behind the student's debt instead of being credited.
    Registration::create([
        'student_id' => summaryStudent()->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $totals = TrainerFinancials::for($this->trainer->fresh());

    expect($totals->credited)->toBe(0.0)
        ->and($totals->balance)->toBe(0.0)
        ->and($totals->pending)->toBe(80.0)
        ->and($totals->expectedTotal())->toBe(80.0);
});

it('counts a manual desk deposit as credited, alongside teaching shares', function () {
    Registration::create([
        'student_id' => summaryStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $this->trainer->depositFloat(120, ['description' => 'Bonus']);

    $totals = TrainerFinancials::for($this->trainer->fresh());

    expect($totals->credited)->toBe(200.0)
        ->and($totals->paidOut)->toBe(0.0)
        ->and($totals->balance)->toBe(200.0);
});

it('keeps credited minus paid out equal to the balance the page shows', function () {
    Registration::create([
        'student_id' => summaryStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $this->trainer->withdrawFloat(30, ['description' => 'Payout']);
    $this->trainer->depositFloat(10, ['description' => 'Correction']);

    $totals = TrainerFinancials::for($this->trainer->fresh());

    expect(round($totals->credited - $totals->paidOut, 2))->toBe($totals->balance);
});

it('attaches a trainer to several branches', function () {
    $governorate = Governorate::create(['name' => ['en' => 'Ramallah', 'ar' => 'رام الله']]);
    $city = City::create(['governorate_id' => $governorate->id, 'name' => ['en' => 'Ramallah', 'ar' => 'رام الله']]);

    $makeBranch = fn (string $en, string $ar) => Branch::create([
        'name' => ['en' => $en, 'ar' => $ar],
        'governorate_id' => $governorate->id,
        'city_id' => $city->id,
    ]);

    $north = $makeBranch('North', 'الشمال');
    $south = $makeBranch('South', 'الجنوب');

    $this->trainer->branches()->sync([$north->id, $south->id]);

    expect($this->trainer->branches()->pluck('branches.id')->all())
        ->toEqualCanonicalizing([$north->id, $south->id])
        ->and($north->trainers()->pluck('trainers.id')->all())
        ->toBe([$this->trainer->id]);
});
