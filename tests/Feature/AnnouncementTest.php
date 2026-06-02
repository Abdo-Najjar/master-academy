<?php

use App\Models\Announcement;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'T', 'ar' => 'مدرب'],
        'username' => 'tn_'.uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create([
        'name' => ['en' => 'S', 'ar' => 'مادة'],
    ]);

    $this->section = Section::create([
        'name' => ['en' => 'Sec', 'ar' => 'قسم'],
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
    ]);

    $this->student = Student::create([
        'name' => ['en' => 'S', 'ar' => 'طالب'],
        'username' => 'st_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'payment_type_id' => PaymentType::create(['name' => 'Cash'])->id,
        'amount_due' => 0,
        'amount_paid' => 0,
        'exemption_amount' => 0,
        'trainer_amount' => 0,
    ]);
});

it('scopes active to exclude expired and unpublished announcements', function () {
    Announcement::create([
        'title' => 'Active now',
        'body' => 'body',
        'all_sections' => true,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);
    Announcement::create([
        'title' => 'Expired',
        'body' => 'body',
        'all_sections' => true,
        'published_at' => now()->subDays(10),
        'expires_at' => now()->subDay(),
    ]);
    Announcement::create([
        'title' => 'Future',
        'body' => 'body',
        'all_sections' => true,
        'published_at' => now()->addDays(2),
    ]);

    $active = Announcement::active()->pluck('title')->all();
    expect($active)->toBe(['Active now']);
});

it('shows announcements targeted at the student via section', function () {
    $targeted = Announcement::create([
        'title' => 'Targeted',
        'body' => 'For my section',
        'all_sections' => false,
        'published_at' => now()->subDay(),
    ]);
    $targeted->sections()->attach($this->section->id);

    $unrelated = Section::create([
        'name' => ['en' => 'Other', 'ar' => 'آخر'],
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
    ]);
    $missed = Announcement::create([
        'title' => 'Other section',
        'body' => 'Not for me',
        'all_sections' => false,
        'published_at' => now()->subDay(),
    ]);
    $missed->sections()->attach($unrelated->id);

    $broadcast = Announcement::create([
        'title' => 'Broadcast',
        'body' => 'All',
        'all_sections' => true,
        'published_at' => now()->subDay(),
    ]);

    $visible = Announcement::active()->forStudent($this->student)->pluck('title')->all();

    expect($visible)->toContain('Targeted')
        ->toContain('Broadcast')
        ->not->toContain('Other section');
});

it('lets a student dismiss an announcement', function () {
    $a = Announcement::create([
        'title' => 'Hideable',
        'body' => 'You can hide me',
        'all_sections' => true,
        'published_at' => now()->subDay(),
    ]);

    $this->student->dismissedAnnouncements()->syncWithoutDetaching([$a->id]);

    expect($this->student->dismissedAnnouncements()->count())->toBe(1);
    expect($this->student->dismissedAnnouncements()->first()->id)->toBe($a->id);
});
