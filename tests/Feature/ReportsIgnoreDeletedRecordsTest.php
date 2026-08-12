<?php

use App\Filament\Admin\Pages\Reports;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\FinancialDueService;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب التقارير', 'en' => 'Reports Trainer'],
        'username' => 'reports_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة التقارير', 'en' => 'Reports Subject']]);

    $this->makeSection = fn (string $name) => Section::create([
        'name' => $name,
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
    ]);

    $this->makeStudent = fn (string $name) => Student::create([
        'name' => ['ar' => $name, 'en' => $name],
        'username' => 'reports_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
});

/** The page is driven straight, not through Livewire: it only reads `filters`, which defaults to the current month. */
function reportsPage(): Reports
{
    $page = new Reports;
    $page->filters = [];

    return $page;
}

/** Money is only "collected" once it is funded, so top the wallet up first. */
function enrol(Student $student, Section $section, float $amount = 100): Registration
{
    $student->depositFloat($amount);

    return Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => $amount,
        'amount_paid' => $amount,
    ]);
}

it('drops revenue from a registration whose section was deleted', function () {
    $live = ($this->makeSection)('شعبة حية');
    $removed = ($this->makeSection)('شعبة محذوفة');

    enrol(($this->makeStudent)('طالب حي'), $live);
    enrol(($this->makeStudent)('طالب شعبة محذوفة'), $removed);

    $before = reportsPage()->getStatsProperty();
    expect($before['revenue'])->toBe(200.0)
        ->and($before['registrations'])->toBe(2);

    $removed->delete();

    $after = reportsPage()->getStatsProperty();
    expect($after['revenue'])->toBe(100.0)
        ->and($after['registrations'])->toBe(1);
});

it('drops revenue from a registration whose student was deleted', function () {
    $section = ($this->makeSection)('شعبة');
    enrol(($this->makeStudent)('طالب باقٍ'), $section);
    $leaving = ($this->makeStudent)('طالب سيُحذف');
    enrol($leaving, $section);

    $leaving->delete();

    $stats = reportsPage()->getStatsProperty();

    expect($stats['revenue'])->toBe(100.0)
        ->and($stats['registrations'])->toBe(1);
});

it('keeps attendance of deleted sections out of the attendance rate', function () {
    $live = ($this->makeSection)('شعبة حية');
    $removed = ($this->makeSection)('شعبة محذوفة');

    $a = ($this->makeStudent)('طالب أ');
    $b = ($this->makeStudent)('طالب ب');
    enrol($a, $live);
    enrol($b, $removed);

    Attendance::create(['section_id' => $live->id, 'student_id' => $a->id, 'date' => now()->toDateString(), 'status' => 'present']);
    Attendance::create(['section_id' => $removed->id, 'student_id' => $b->id, 'date' => now()->toDateString(), 'status' => 'absent']);

    expect(reportsPage()->getStatsProperty()['attendance_total'])->toBe(2);

    $removed->delete();

    $stats = reportsPage()->getStatsProperty();

    expect($stats['attendance_total'])->toBe(1)
        ->and($stats['attendance_rate'])->toBe(100.0);
});

it('excludes deleted registrations from the course breakdown', function () {
    $section = ($this->makeSection)('شعبة');
    enrol(($this->makeStudent)('طالب أ'), $section);
    $second = enrol(($this->makeStudent)('طالب ب'), $section);

    $rows = reportsPage()->getSubjectBreakdownProperty();
    expect((int) $rows->first()->total)->toBe(2);

    $second->delete();

    $rows = reportsPage()->getSubjectBreakdownProperty();
    expect((int) $rows->first()->total)->toBe(1);
});

it('excludes deleted registrations from trainer revenue', function () {
    $section = ($this->makeSection)('شعبة');
    enrol(($this->makeStudent)('طالب أ'), $section);
    $second = enrol(($this->makeStudent)('طالب ب'), $section);

    $trainers = reportsPage()->getTopTrainersProperty();
    expect((int) $trainers->first()->registrations_count)->toBe(2)
        ->and((float) $trainers->first()->revenue)->toBe(200.0);

    $second->delete();

    $trainers = reportsPage()->getTopTrainersProperty();
    expect((int) $trainers->first()->registrations_count)->toBe(1)
        ->and((float) $trainers->first()->revenue)->toBe(100.0);
});

it('stops counting outstanding money once the section is deleted', function () {
    $section = ($this->makeSection)('شعبة');

    // No wallet top-up: the charge is unfunded, so it counts as outstanding.
    $student = ($this->makeStudent)('طالب مدين');
    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 300,
        'amount_paid' => 300,
    ]);

    expect(FinancialDueService::outstandingAmount())->toBe(300.0);

    $section->delete();

    expect(FinancialDueService::outstandingAmount())->toBe(0.0);
});

it('drops deleted registrations out of the due students list', function () {
    $section = ($this->makeSection)('شعبة');
    $student = ($this->makeStudent)('طالب مدين');

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 300,
        'amount_paid' => 300,
    ]);

    expect(reportsPage()->getDueStudentsProperty())->toHaveCount(1);

    $registration->delete();

    expect(reportsPage()->getDueStudentsProperty())->toHaveCount(0);
});
