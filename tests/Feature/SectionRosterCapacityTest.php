<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;

/**
 * What a section says about its own roster: how many students are in it, and
 * how many it is allowed to hold at either end.
 */
beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ السعة', 'en' => 'Capacity Trainer'],
        'username' => 'cap_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $this->section = Section::create([
        'name' => 'شعبة السعة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'min_capacity' => 3,
        'capacity' => 5,
    ]);
});

/** Enrol `$count` fresh students into the section under test. */
function fillSection(Section $section, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Registration::create([
            'student_id' => Student::create([
                'name' => ['ar' => 'طالب '.$i, 'en' => 'Student '.$i],
                'username' => 'cap_stu_'.uniqid(),
                'password' => 'password',
            ])->id,
            'section_id' => $section->id,
            'amount_due' => 0,
            'amount_paid' => 0,
        ]);
    }
}

it('counts the students actually sitting in the section', function () {
    fillSection($this->section, 4);

    expect($this->section->enrolledCount())->toBe(4)
        ->and($this->section->seatsSummary())->toBe('4 / 5');
});

it('does not count students who withdrew', function () {
    fillSection($this->section, 4);

    Registration::query()->where('section_id', $this->section->id)
        ->first()
        ->update(['left_at' => now()->toDateString()]);

    // The seat went back on the shelf.
    expect($this->section->enrolledCount())->toBe(3);
});

it('flags a section short of its minimum', function () {
    fillSection($this->section, 2);

    expect($this->section->isBelowMinimum())->toBeTrue()
        ->and($this->section->isFull())->toBeFalse();
});

it('flags a section once every seat is taken', function () {
    fillSection($this->section, 5);

    expect($this->section->isFull())->toBeTrue()
        ->and($this->section->isBelowMinimum())->toBeFalse();
});

it('treats a section with no limits as neither short nor full', function () {
    $open = Section::create([
        'name' => 'شعبة مفتوحة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
    ]);

    fillSection($open, 2);

    expect($open->isBelowMinimum())->toBeFalse()
        ->and($open->isFull())->toBeFalse()
        // No ceiling, so no "of N" to report.
        ->and($open->seatsSummary())->toBe('2');
});
