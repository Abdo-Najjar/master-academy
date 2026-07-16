<?php

use App\Filament\Admin\Resources\Assignments\AssignmentResource;
use App\Filament\Admin\Resources\Assignments\RelationManagers\SubmissionsRelationManager;
use App\Filament\Admin\Resources\Assignments\Pages\ViewAssignment;
use App\Filament\Admin\Resources\Trainers\Pages\ViewTrainer;
use App\Filament\Admin\Resources\Trainers\RelationManagers\AssignmentsRelationManager;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()]
    );
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->trainer = Trainer::create(['name' => 'مدرب تكاليف', 'username' => 'asg_trainer', 'is_active' => true]);
    $this->subject = Subject::create(['name' => 'مادة تكاليف']);
    $this->section = Section::create(['name' => 'شعبة تكاليف', 'trainer_id' => $this->trainer->id, 'subject_id' => $this->subject->id, 'price' => 100]);
    $this->student = Student::create(['name' => 'طالب تكاليف', 'student_number' => 'STU-ASG-1']);
    Registration::create(['section_id' => $this->section->id, 'student_id' => $this->student->id]);

    $this->assignment = Assignment::create([
        'section_id' => $this->section->id,
        'trainer_id' => $this->trainer->id,
        'title' => 'تكليف اختبار',
        'due_date' => now()->addDays(3),
        'max_points' => 20,
    ]);

    $this->submission = AssignmentSubmission::create([
        'assignment_id' => $this->assignment->id,
        'student_id' => $this->student->id,
        'content' => 'إجابتي هنا',
        'submitted_at' => now(),
    ]);
});

it('lists submissions in the admin SubmissionsRelationManager', function () {
    Livewire::test(SubmissionsRelationManager::class, [
        'ownerRecord' => $this->assignment,
        'pageClass' => ViewAssignment::class,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$this->submission])
        ->assertTableActionExists('grade', record: $this->submission);
});

it('grades a submission via the admin action', function () {
    Livewire::test(SubmissionsRelationManager::class, [
        'ownerRecord' => $this->assignment,
        'pageClass' => ViewAssignment::class,
    ])
        ->loadTable()
        ->callTableAction('grade', record: $this->submission, data: ['grade' => 18, 'feedback' => 'ممتاز']);

    expect($this->submission->fresh()->grade)->toEqual(18.0)
        ->and($this->submission->fresh()->feedback)->toBe('ممتاز');
});

it('lists trainer assignments in the ViewTrainer relation manager', function () {
    Livewire::test(AssignmentsRelationManager::class, [
        'ownerRecord' => $this->trainer,
        'pageClass' => ViewTrainer::class,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$this->assignment])
        ->assertTableActionExists('viewSubmissions', record: $this->assignment);
});

it('creates an assignment for the trainer via the relation manager action', function () {
    Livewire::test(AssignmentsRelationManager::class, [
        'ownerRecord' => $this->trainer,
        'pageClass' => ViewTrainer::class,
    ])
        ->loadTable()
        ->callTableAction('createAssignment', data: [
            'section_id' => $this->section->id,
            'title' => 'تكليف جديد من المدرب',
        ]);

    expect(Assignment::where('title', 'تكليف جديد من المدرب')->where('section_id', $this->section->id)->exists())->toBeTrue();
});

it('shows assignment detail page with resource url', function () {
    expect(AssignmentResource::getUrl('view', ['record' => $this->assignment]))->toContain('/admin/assignments/'.$this->assignment->id);
});
