<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SessionBillingService;
use App\Support\TrainerRate;

/**
 * The trainer's cut, quoted as a fraction or as a percentage. This drives real
 * money into a real wallet, so the tests follow it all the way from the value
 * that gets stored to the piaster that lands in the trainer's balance.
 *
 * Fixtures come from plain functions rather than `$this->` properties set in a
 * `beforeEach` — same result, and it keeps editors from flagging every use as
 * an undefined property.
 */
function rateSubject(): Subject
{
    return Subject::create(['name' => ['ar' => 'مادة النسب', 'en' => 'Rates']]);
}

function rateTrainer(float $defaultRate = 0): Trainer
{
    return Trainer::create([
        'name' => ['ar' => 'أستاذ النسبة', 'en' => 'Rate Trainer'],
        'username' => 'rate_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => $defaultRate,
    ]);
}

function rateStudent(): Student
{
    return Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'rate_s_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
}

it('maps every named fraction to the right percentage', function () {
    expect(TrainerRate::percent('half'))->toBe(50.0)
        ->and(TrainerRate::percent('third'))->toBe(33.3333)
        ->and(TrainerRate::percent('two_thirds'))->toBe(66.6667)
        ->and(TrainerRate::percent('quarter'))->toBe(25.0)
        ->and(TrainerRate::percent('three_quarters'))->toBe(75.0)
        ->and(TrainerRate::percent('fifth'))->toBe(20.0)
        ->and(TrainerRate::percent('two_fifths'))->toBe(40.0)
        ->and(TrainerRate::percent('sixth'))->toBe(16.6667)
        ->and(TrainerRate::percent('tenth'))->toBe(10.0)
        // 45% is 9/20 — no fraction anybody names, so it is offered as a plain
        // percentage preset instead.
        ->and(TrainerRate::percent('pct_45'))->toBe(45.0)
        ->and(TrainerRate::percent('nonsense'))->toBeNull();
});

it('offers every preset in the picker, highest share first', function () {
    $options = TrainerRate::options();

    expect(array_values(array_map(
        fn (string $key): float => (float) TrainerRate::percent($key),
        array_keys($options),
    )))->toBe([75.0, 66.6667, 50.0, 45.0, 40.0, 33.3333, 25.0, 20.0, 16.6667, 10.0]);
});

it('recognises a stored percentage as the fraction it stands for', function () {
    expect(TrainerRate::match(50))->toBe('half')
        ->and(TrainerRate::match(33.3333))->toBe('third')
        ->and(TrainerRate::match(25))->toBe('quarter')
        // The centre's usual 40% is two fifths, so the picker shows it back.
        ->and(TrainerRate::match(40))->toBe('two_fifths')
        ->and(TrainerRate::match(45))->toBe('pct_45')
        // A percentage that is not on the list stays unmatched.
        ->and(TrainerRate::match(37.5))->toBeNull()
        ->and(TrainerRate::match(30))->toBeNull()
        ->and(TrainerRate::match(null))->toBeNull()
        // Legacy rows stored at two decimals still read as a third.
        ->and(TrainerRate::match(33.33))->toBe('third');
});

it('labels a rate the way it was quoted', function () {
    app()->setLocale('en');

    // Only the rates the number alone cannot express get named. 50% and 75%
    // say themselves; 33.3333% is a third that has been rounded.
    expect(TrainerRate::label(33.3333))->toBe('Third (33.3333%)')
        ->and(TrainerRate::label(66.6667))->toBe('Two thirds (66.6667%)')
        ->and(TrainerRate::label(16.6667))->toBe('Sixth (16.6667%)')
        ->and(TrainerRate::label(50))->toBe('50%')
        ->and(TrainerRate::label(75))->toBe('75%')
        ->and(TrainerRate::label(45))->toBe('45%')
        ->and(TrainerRate::label(40))->toBe('40%')
        // No trailing zeros: the column holds 40.0000.
        ->and(TrainerRate::label('40.0000'))->toBe('40%')
        ->and(TrainerRate::label(12.5))->toBe('12.5%')
        ->and(TrainerRate::label(null))->toBeNull();
});

it('stores a third at full precision instead of rounding it to 33.33', function () {
    $section = Section::create([
        'name' => 'شعبة الثلث',
        'subject_id' => rateSubject()->id,
        'trainer_id' => rateTrainer()->id,
        'price' => 300,
        'trainer_rate' => TrainerRate::percent('third'),
    ]);

    expect((float) $section->fresh()->trainer_rate)->toBe(33.3333);
});

/**
 * The case that made the precision matter: a third of a 300 ₪ fee is exactly
 * 100 ₪, and 33.33% of it is not.
 */
it('pays a trainer on a third of a 300 fee the full 100', function () {
    $trainer = rateTrainer();

    $section = Section::create([
        'name' => 'شعبة الثلث',
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => 300,
        'trainer_rate' => TrainerRate::percent('third'),
    ]);

    $student = rateStudent();
    // Funds first, so the trainer's share is credited rather than left pending.
    $student->depositFloat(300);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 300,
        'amount_paid' => 300,
    ]);

    expect((float) $registration->fresh()->trainer_amount)->toBe(100.0)
        ->and((float) $trainer->fresh()->balanceFloat)->toBe(100.0);
});

it('pays each fraction its exact share', function (string $fraction, float $fee, float $expected) {
    $trainer = rateTrainer();

    $section = Section::create([
        'name' => 'شعبة '.$fraction,
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => $fee,
        'trainer_rate' => TrainerRate::percent($fraction),
    ]);

    $student = rateStudent();
    $student->depositFloat($fee);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => $fee,
        'amount_paid' => $fee,
    ]);

    expect((float) $trainer->fresh()->balanceFloat)->toBe($expected);
})->with([
    ['half', 200.0, 100.0],
    ['third', 300.0, 100.0],
    ['third', 600.0, 200.0],
    ['two_thirds', 300.0, 200.0],
    ['quarter', 400.0, 100.0],
    ['three_quarters', 400.0, 300.0],
    ['fifth', 500.0, 100.0],
    ['sixth', 600.0, 100.0],
    ['tenth', 500.0, 50.0],
]);

it('still accepts a plain typed percentage', function () {
    $trainer = rateTrainer();

    $section = Section::create([
        'name' => 'شعبة نسبة مئوية',
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => 250,
        'trainer_rate' => 37.5,
    ]);

    $student = rateStudent();
    $student->depositFloat(250);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 250,
        'amount_paid' => 250,
    ]);

    expect((float) $section->fresh()->trainer_rate)->toBe(37.5)
        ->and((float) $trainer->fresh()->balanceFloat)->toBe(93.75);
});

it('falls back to the trainer default rate, fraction and all', function () {
    $trainer = rateTrainer(TrainerRate::percent('quarter'));

    $section = Section::create([
        'name' => 'شعبة بلا نسبة',
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => 400,
        'trainer_rate' => null,
    ]);

    expect($section->effectiveTrainerRate())->toBe(25.0);

    $student = rateStudent();
    $student->depositFloat(400);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    expect((float) $trainer->fresh()->balanceFloat)->toBe(100.0);
});

it('uses a private session own fraction over the section rate', function () {
    $trainer = rateTrainer();

    $section = Section::create([
        'name' => 'شعبة خاصة',
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => 0,
        'trainer_rate' => TrainerRate::percent('tenth'),
    ]);

    $student = rateStudent();

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $session = SectionSession::create([
        'section_id' => $section->id,
        'date' => '2026-09-01',
        'type' => SectionSession::TYPE_PRIVATE,
        'status' => SectionSession::STATUS_HELD,
        'fee' => 300,
        'trainer_rate' => TrainerRate::percent('third'),
    ]);

    SessionBillingService::chargePrivateSession($session, [$student->id]);

    expect($session->effectiveTrainerRate())->toBe(33.3333)
        // A third of the session fee, not the section's tenth.
        ->and((float) $trainer->fresh()->balanceFloat)->toBe(100.0)
        ->and((float) $student->fresh()->balanceFloat)->toBe(-300.0);
});

it('keeps the trainer on their fraction when further cycles are charged', function () {
    $trainer = rateTrainer();

    $section = Section::create([
        'name' => 'شعبة بالحصة',
        'subject_id' => rateSubject()->id,
        'trainer_id' => $trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 300,
        'trainer_rate' => TrainerRate::percent('third'),
    ]);

    $student = rateStudent();
    $student->depositFloat(900);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-09-01',
        'amount_due' => 300,
        'amount_paid' => 300,
    ]);

    expect((float) $registration->fresh()->trainer_amount)->toBe(100.0);

    SessionBillingService::payCycle($registration->fresh(), 2);

    // Three cycles at 300 → 900 charged, a third of which is 300.
    expect((float) $registration->fresh()->amount_paid)->toBe(900.0)
        ->and((float) $registration->fresh()->trainer_amount)->toBe(300.0)
        ->and((float) $trainer->fresh()->balanceFloat)->toBe(300.0);
});
