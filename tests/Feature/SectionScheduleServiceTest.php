<?php

use App\Models\Attendance;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SectionScheduleService;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['en' => 'Schedule Trainer', 'ar' => 'مدرب الجدول'],
        'username' => 'schedule_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create([
        'name' => ['en' => 'Schedule Subject', 'ar' => 'مادة الجدول'],
    ]);

    // 2026-08-01 is a Saturday; the range covers exactly two full weeks.
    $this->section = Section::create([
        'name' => 'S1',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-14',
        'price' => 100,
    ]);

    foreach (['saturday', 'monday', 'wednesday'] as $day) {
        SectionTime::create([
            'section_id' => $this->section->id,
            'day' => $day,
            'start_time' => '15:30',
            'end_time' => '17:00',
        ]);
    }

    $this->student = Student::create([
        'name' => ['en' => 'Schedule Student', 'ar' => 'طالب الجدول'],
        'username' => 'schedule_'.uniqid(),
        'password' => 'password',
    ]);
});

it('expands the weekly pattern across the section date range', function () {
    $dates = SectionScheduleService::plannedDates($this->section);

    expect($dates->all())->toBe([
        '2026-08-01', '2026-08-03', '2026-08-05',
        '2026-08-08', '2026-08-10', '2026-08-12',
    ]);
});

it('returns nothing when the section has no date range', function () {
    $this->section->update(['start_date' => null, 'end_date' => null]);

    expect(SectionScheduleService::plannedDates($this->section->fresh()))->toBeEmpty();
});

it('counts held sessions and what is left', function () {
    // `next_date` is "the first outstanding date from today onwards", so the
    // clock has to sit inside the section's fixed date range for the expected
    // value to be stable — otherwise this test expires on 2026-08-05.
    $this->travelTo('2026-08-04 09:00:00');

    Attendance::create(['section_id' => $this->section->id, 'student_id' => $this->student->id, 'status' => 'present', 'date' => '2026-08-01']);
    Attendance::create(['section_id' => $this->section->id, 'student_id' => $this->student->id, 'status' => 'absent', 'date' => '2026-08-03']);

    $summary = SectionScheduleService::summary($this->section->fresh());

    expect($summary['held'])->toBe(2)
        ->and($summary['planned'])->toBe(6)
        ->and($summary['remaining'])->toBe(4)
        ->and($summary['next_date'])->toBe('2026-08-05');
});

it('tallies each held session by status, newest first', function () {
    $other = Student::create([
        'name' => ['en' => 'Second', 'ar' => 'ثاني'],
        'username' => 'schedule2_'.uniqid(),
        'password' => 'password',
    ]);

    Attendance::create(['section_id' => $this->section->id, 'student_id' => $this->student->id, 'status' => 'present', 'date' => '2026-08-01']);
    Attendance::create(['section_id' => $this->section->id, 'student_id' => $other->id, 'status' => 'absent', 'date' => '2026-08-01']);
    Attendance::create(['section_id' => $this->section->id, 'student_id' => $this->student->id, 'status' => 'late', 'date' => '2026-08-03']);

    $held = SectionScheduleService::heldSessions($this->section->fresh());

    expect($held->pluck('date')->all())->toBe(['2026-08-03', '2026-08-01'])
        ->and($held->firstWhere('date', '2026-08-01'))
        ->toMatchArray(['present' => 1, 'absent' => 1, 'total' => 2]);
});

it('counts an off-pattern make-up session without going negative', function () {
    // 2026-08-04 is a Tuesday — not part of the weekly pattern.
    Attendance::create(['section_id' => $this->section->id, 'student_id' => $this->student->id, 'status' => 'present', 'date' => '2026-08-04']);

    $summary = SectionScheduleService::summary($this->section->fresh());

    expect($summary['held'])->toBe(1)
        ->and($summary['planned'])->toBe(7)
        ->and($summary['remaining'])->toBe(6);
});
