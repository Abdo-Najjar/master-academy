<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\StudentSectionTransfer;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\StudentTransferService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب النقل', 'en' => 'Transfer Trainer'],
        'username' => 'transfer_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة النقل', 'en' => 'Transfer Subject']]);

    $make = fn (string $name) => Section::create([
        'name' => $name,
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 6,
        'cycle_fee' => 100,
    ]);

    $this->from = $make('الشعبة الأولى');
    $this->to = $make('الشعبة الثانية');

    $this->student = Student::create([
        'name' => ['ar' => 'طالب النقل', 'en' => 'Transfer Student'],
        'username' => 'transfer_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->from->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);
});

it('carries the session counter over instead of restarting it', function () {
    // Four lessons held in the original section.
    for ($i = 0; $i < 4; $i++) {
        SectionSession::create([
            'section_id' => $this->from->id,
            'date' => Carbon::parse('2026-09-01')->addDays($i)->toDateString(),
        ]);
    }

    expect($this->registration->fresh()->sessions_counted)->toBe(4);

    StudentTransferService::transfer($this->registration->fresh(), $this->to->id, 'تغيير الدوام');

    $moved = $this->registration->fresh();

    expect($moved->section_id)->toBe($this->to->id)
        ->and($moved->sessions_counted)->toBe(4)
        ->and($moved->paid_through_session)->toBe(6);

    // The next lesson in the new section continues from 4, not from 0.
    SectionSession::create(['section_id' => $this->to->id, 'date' => '2026-10-01']);

    expect($this->registration->fresh()->sessions_counted)->toBe(5);
});

it('records who moved the student, from where, to where and why', function () {
    StudentTransferService::transfer($this->registration->fresh(), $this->to->id, 'ازدحام الشعبة');

    $transfer = StudentSectionTransfer::query()->latest('id')->first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->student_id)->toBe($this->student->id)
        ->and($transfer->from_section_id)->toBe($this->from->id)
        ->and($transfer->to_section_id)->toBe($this->to->id)
        ->and($transfer->reason)->toBe('ازدحام الشعبة')
        ->and($transfer->transferred_at)->not->toBeNull();
});

it('refuses to transfer into a section the student is already in', function () {
    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->to->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    expect(fn () => StudentTransferService::transfer($this->registration->fresh(), $this->to->id))
        ->toThrow(ValidationException::class);
});

it('refuses to transfer into a section of a different course', function () {
    $otherSubject = Subject::create(['name' => ['ar' => 'مادة أخرى', 'en' => 'Other Subject']]);

    $otherCourseSection = Section::create([
        'name' => 'شعبة دورة أخرى',
        'subject_id' => $otherSubject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 6,
        'cycle_fee' => 100,
    ]);

    expect(fn () => StudentTransferService::transfer($this->registration->fresh(), $otherCourseSection->id))
        ->toThrow(ValidationException::class);

    expect($this->registration->fresh()->section_id)->toBe($this->from->id);
});

it('refuses to transfer into a full section', function () {
    $this->to->update(['capacity' => 1]);

    $other = Student::create([
        'name' => ['ar' => 'طالب آخر', 'en' => 'Other'],
        'username' => 'transfer_other_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $other->id,
        'section_id' => $this->to->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    expect(fn () => StudentTransferService::transfer($this->registration->fresh(), $this->to->id))
        ->toThrow(ValidationException::class);
});
