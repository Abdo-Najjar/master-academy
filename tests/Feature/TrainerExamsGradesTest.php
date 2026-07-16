<?php

use App\Filament\Admin\Pages\GradesRecords;
use App\Filament\Admin\Resources\Trainers\Pages\ViewTrainer;
use App\Filament\Admin\Resources\Trainers\RelationManagers\ExamsRelationManager;
use App\Models\Exam;
use App\Models\ExamGrade;
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

    $this->trainer = Trainer::create([
        'name' => 'مدرب اختبار',
        'username' => 'test_trainer_grades',
        'is_active' => true,
    ]);
    $this->subject = Subject::create(['name' => 'مادة اختبار']);
    $this->section = Section::create([
        'name' => 'شعبة اختبار',
        'trainer_id' => $this->trainer->id,
        'subject_id' => $this->subject->id,
        'price' => 100,
    ]);
    $this->student = Student::create([
        'name' => 'طالب اختبار',
        'student_number' => 'STU-TEST-1',
    ]);
    Registration::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
    ]);
    $this->exam = Exam::create([
        'section_id' => $this->section->id,
        'name' => 'امتحان الوحدة الأولى',
        'date' => now()->toDateString(),
        'max_score' => 100,
    ]);
    $this->grade = ExamGrade::create([
        'exam_id' => $this->exam->id,
        'student_id' => $this->student->id,
        'score' => 87.5,
    ]);
});

it('lists the trainer exams in the Exams & Grades relation manager', function () {
    Livewire::test(ExamsRelationManager::class, [
        'ownerRecord' => $this->trainer,
        'pageClass' => ViewTrainer::class,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$this->exam])
        ->assertTableActionExists('bulkGrade', record: $this->exam)
        ->assertTableColumnExists('grades_count');
});

it('creates an exam for the trainer via the relation manager action', function () {
    Livewire::test(ExamsRelationManager::class, [
        'ownerRecord' => $this->trainer,
        'pageClass' => ViewTrainer::class,
    ])
        ->loadTable()
        ->callTableAction('createExam', data: [
            'section_id' => $this->section->id,
            'name' => 'امتحان جديد من المدرب',
            'date' => now()->toDateString(),
            'max_score' => 50,
        ]);

    expect(Exam::where('name', 'امتحان جديد من المدرب')->where('section_id', $this->section->id)->exists())->toBeTrue();
});

it('shows all grades in the admin Grades page', function () {
    Livewire::test(GradesRecords::class)
        ->assertCanSeeTableRecords([$this->grade])
        ->assertSee('امتحان الوحدة الأولى')
        ->assertSee('مدرب اختبار');
});
