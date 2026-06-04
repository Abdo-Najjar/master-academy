<?php

use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Subject;
use App\Models\Trainer;
use Illuminate\Validation\ValidationException;

function sectionSubject(): Subject
{
    return Subject::create(['name' => ['en' => 'Math', 'ar' => 'رياضيات']]);
}

it('computes the status accessor from dates', function () {
    $subject = sectionSubject();

    $upcoming = Section::create([
        'name' => ['en' => 'U', 'ar' => 'ق'],
        'subject_id' => $subject->id,
        'start_date' => now()->addDays(5),
        'end_date' => now()->addDays(40),
    ]);
    $active = Section::create([
        'name' => ['en' => 'A', 'ar' => 'ن'],
        'subject_id' => $subject->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(10),
    ]);
    $completed = Section::create([
        'name' => ['en' => 'C', 'ar' => 'م'],
        'subject_id' => $subject->id,
        'start_date' => now()->subDays(40),
        'end_date' => now()->subDays(5),
    ]);
    $scheduled = Section::create([
        'name' => ['en' => 'S', 'ar' => 'ج'],
        'subject_id' => $subject->id,
    ]);

    expect($upcoming->status)->toBe('upcoming')
        ->and($active->status)->toBe('active')
        ->and($completed->status)->toBe('completed')
        ->and($scheduled->status)->toBe('scheduled');
});

it('blocks a trainer from being double-booked at overlapping times', function () {
    $subject = sectionSubject();
    $trainer = Trainer::create([
        'name' => ['en' => 'T', 'ar' => 'م'],
        'username' => 'trn_'.uniqid(),
    ]);

    $sectionA = Section::create(['name' => ['en' => 'A', 'ar' => 'أ'], 'subject_id' => $subject->id, 'trainer_id' => $trainer->id]);
    $sectionB = Section::create(['name' => ['en' => 'B', 'ar' => 'ب'], 'subject_id' => $subject->id, 'trainer_id' => $trainer->id]);

    SectionTime::create([
        'section_id' => $sectionA->id,
        'day' => 'monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    expect(fn () => SectionTime::create([
        'section_id' => $sectionB->id,
        'day' => 'monday',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]))->toThrow(ValidationException::class);
});

it('allows the same trainer at non-overlapping times', function () {
    $subject = sectionSubject();
    $trainer = Trainer::create([
        'name' => ['en' => 'T', 'ar' => 'م'],
        'username' => 'trn_'.uniqid(),
    ]);

    $sectionA = Section::create(['name' => ['en' => 'A', 'ar' => 'أ'], 'subject_id' => $subject->id, 'trainer_id' => $trainer->id]);
    $sectionB = Section::create(['name' => ['en' => 'B', 'ar' => 'ب'], 'subject_id' => $subject->id, 'trainer_id' => $trainer->id]);

    SectionTime::create(['section_id' => $sectionA->id, 'day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00']);
    $second = SectionTime::create(['section_id' => $sectionB->id, 'day' => 'monday', 'start_time' => '10:00', 'end_time' => '12:00']);

    expect($second->exists)->toBeTrue();
});
