<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Bavix\Wallet\Models\Transaction;

function makeSection(array $overrides = []): Section
{
    $subject = Subject::create(['name' => ['en' => 'Math', 'ar' => 'رياضيات']]);
    $trainer = Trainer::create([
        'name' => ['en' => 'Trainer', 'ar' => 'مدرب'],
        'username' => 'trn_'.uniqid(),
        'default_rate' => 25,
    ]);

    return Section::create(array_merge([
        'name' => ['en' => 'Section A', 'ar' => 'شعبة أ'],
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => 1000,
        'trainer_rate' => 25,
    ], $overrides));
}

it('charges the student wallet for amount_paid on registration', function () {
    $section = makeSection();
    $student = Student::create(['name' => ['en' => 'S', 'ar' => 'ط'], 'username' => 'st_'.uniqid()]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 1000,
        'amount_paid' => 800,
        'trainer_amount' => 0,
    ]);

    expect((float) $student->fresh()->balanceFloat)->toBe(-800.0);
});

it('credits the trainer wallet for trainer_amount on registration', function () {
    $section = makeSection();
    $student = Student::create(['name' => ['en' => 'S', 'ar' => 'ط'], 'username' => 'st_'.uniqid()]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 1000,
        'amount_paid' => 1000,
        'trainer_amount' => 250,
    ]);

    expect((float) $section->trainer->fresh()->balanceFloat)->toBe(250.0);
});

it('records the student name in the wallet transaction note', function () {
    $section = makeSection();
    $student = Student::create(['name' => ['en' => 'John Doe', 'ar' => 'جون'], 'username' => 'st_'.uniqid()]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 500,
        'amount_paid' => 500,
        'trainer_amount' => 0,
    ]);

    $tx = Transaction::where('wallet_id', $student->wallet->id)->latest('id')->first();

    // The note uses the student's name in the current locale.
    $expectedName = $student->getTranslation('name', app()->getLocale(), false);

    expect($tx->meta['note'])
        ->toContain((string) $registration->id)
        ->toContain($expectedName);
});

it('does not move money when amounts are zero', function () {
    $section = makeSection();
    $student = Student::create(['name' => ['en' => 'S', 'ar' => 'ط'], 'username' => 'st_'.uniqid()]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 1000,
        'amount_paid' => 0,
        'trainer_amount' => 0,
    ]);

    expect((float) $student->fresh()->balanceFloat)->toBe(0.0)
        ->and((float) $section->trainer->fresh()->balanceFloat)->toBe(0.0);
});
