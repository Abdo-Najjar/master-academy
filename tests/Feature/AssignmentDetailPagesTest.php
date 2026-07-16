<?php

use App\Livewire\StudentAssignmentSubmission;
use App\Livewire\TrainerAssignmentSubmissions;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = Trainer::create(['name' => 'مدرب صفحات', 'username' => 'pages_trainer', 'password' => 'password', 'is_active' => true]);
    $this->subject = Subject::create(['name' => 'مادة صفحات']);
    $this->section = Section::create(['name' => 'شعبة صفحات', 'trainer_id' => $this->trainer->id, 'subject_id' => $this->subject->id, 'price' => 100]);

    $this->studentA = Student::create(['name' => 'طالب سلّم', 'student_number' => 'STU-PAGE-1', 'password' => 'password', 'is_active' => true]);
    $this->studentB = Student::create(['name' => 'طالب ما سلّم', 'student_number' => 'STU-PAGE-2', 'password' => 'password', 'is_active' => true]);

    Registration::create(['section_id' => $this->section->id, 'student_id' => $this->studentA->id]);
    Registration::create(['section_id' => $this->section->id, 'student_id' => $this->studentB->id]);

    $this->assignment = Assignment::create([
        'section_id' => $this->section->id,
        'trainer_id' => $this->trainer->id,
        'title' => 'تكليف الصفحة',
        'max_points' => 20,
    ]);

    $this->submission = AssignmentSubmission::create([
        'assignment_id' => $this->assignment->id,
        'student_id' => $this->studentA->id,
        'content' => 'إجابة الطالب الأول',
        'submitted_at' => now(),
    ]);
});

it('shows every registered student on the trainer submissions page, including those who have not submitted', function () {
    $this->actingAs($this->trainer, 'trainer');

    Livewire::test(TrainerAssignmentSubmissions::class, ['assignment' => $this->assignment])
        ->assertSee('طالب سلّم')
        ->assertSee('طالب ما سلّم')
        ->assertSee('إجابة الطالب الأول');
});

it('filters trainer submissions page by status', function () {
    $this->actingAs($this->trainer, 'trainer');

    Livewire::test(TrainerAssignmentSubmissions::class, ['assignment' => $this->assignment])
        ->set('statusFilter', 'not_submitted')
        ->assertDontSee('إجابة الطالب الأول')
        ->assertSee('طالب ما سلّم');
});

it('filters trainer submissions page by student search', function () {
    $this->actingAs($this->trainer, 'trainer');

    Livewire::test(TrainerAssignmentSubmissions::class, ['assignment' => $this->assignment])
        ->set('studentSearch', 'STU-PAGE-2')
        ->assertDontSee('إجابة الطالب الأول');
});

it('lets the trainer grade a submission from the dedicated page', function () {
    $this->actingAs($this->trainer, 'trainer');

    Livewire::test(TrainerAssignmentSubmissions::class, ['assignment' => $this->assignment])
        ->set("gradeInputs.{$this->submission->id}", 18)
        ->set("feedbackInputs.{$this->submission->id}", 'ممتاز')
        ->call('saveGrade', $this->submission->id);

    expect($this->submission->fresh()->grade)->toEqual(18.0)
        ->and($this->submission->fresh()->feedback)->toBe('ممتاز');
});

it('blocks a trainer from viewing another trainers assignment page', function () {
    $other = Trainer::create(['name' => 'مدرب آخر', 'username' => 'other_pages_trainer', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($other, 'trainer');

    Livewire::test(TrainerAssignmentSubmissions::class, ['assignment' => $this->assignment])
        ->assertStatus(403);
});

it('lets the student submit their own assignment via the dedicated page', function () {
    $this->actingAs($this->studentB, 'student');

    Livewire::test(StudentAssignmentSubmission::class, ['assignment' => $this->assignment])
        ->set('content', 'إجابة الطالب الثاني')
        ->call('submit');

    expect(AssignmentSubmission::where('assignment_id', $this->assignment->id)->where('student_id', $this->studentB->id)->first()?->content)
        ->toBe('إجابة الطالب الثاني');
});

it('preloads the students existing submission for editing', function () {
    $this->actingAs($this->studentA, 'student');

    Livewire::test(StudentAssignmentSubmission::class, ['assignment' => $this->assignment])
        ->assertSet('content', 'إجابة الطالب الأول');
});

it('blocks a student from submitting to an assignment outside their sections', function () {
    $outsider = Student::create(['name' => 'طالب غريب', 'student_number' => 'STU-PAGE-3', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($outsider, 'student');

    Livewire::test(StudentAssignmentSubmission::class, ['assignment' => $this->assignment])
        ->assertStatus(403);
});
