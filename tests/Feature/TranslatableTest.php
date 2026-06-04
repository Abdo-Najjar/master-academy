<?php

use App\Models\Student;
use App\Models\Subject;

it('stores and reads both locales for a translatable name', function () {
    $subject = Subject::create(['name' => ['ar' => 'رياضيات', 'en' => 'Math']]);

    expect($subject->getTranslation('name', 'ar'))->toBe('رياضيات')
        ->and($subject->getTranslation('name', 'en'))->toBe('Math')
        ->and($subject->getTranslations('name'))->toBe(['ar' => 'رياضيات', 'en' => 'Math']);
});

it('returns the current-locale value for the name attribute', function () {
    $subject = Subject::create(['name' => ['ar' => 'رياضيات', 'en' => 'Math']]);

    app()->setLocale('en');
    expect($subject->fresh()->name)->toBe('Math');

    app()->setLocale('ar');
    expect($subject->fresh()->name)->toBe('رياضيات');
});

it('persists the raw translations array so Filament tab fields can fill', function () {
    $student = Student::create([
        'name' => ['ar' => 'محمد', 'en' => 'Mohammed'],
        'username' => 'tr_'.uniqid(),
    ]);

    // attributesToArray() is what Filament uses to fill the form — must be the array.
    expect($student->fresh()->attributesToArray()['name'])
        ->toBe(['ar' => 'محمد', 'en' => 'Mohammed']);
});
