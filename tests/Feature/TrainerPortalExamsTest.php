<?php

use App\Livewire\StudentDashboard;
use App\Livewire\TrainerDashboard;
use App\Models\Exam;
use App\Models\ExamGrade;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب الامتحانات', 'en' => 'Exams Trainer'],
        'username' => 'exams_trainer_'.uniqid(),
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->otherTrainer = Trainer::create([
        'name' => ['ar' => 'مدرب غريب', 'en' => 'Other'],
        'username' => 'exams_other_'.uniqid(),
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    $this->section = Section::create([
        'name' => 'شعبة الامتحانات',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
    ]);

    $this->foreignSection = Section::create([
        'name' => 'شعبة غريبة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->otherTrainer->id,
        'price' => 100,
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'exams_student_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);
});

it('lets a trainer create an exam for their own section', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('newExamSectionId', $this->section->id)
        ->set('newExamName', 'اختبار الوحدة الأولى')
        ->set('newExamDate', '2026-09-10')
        ->set('newExamMaxScore', '50')
        ->call('createExam')
        ->assertHasNoErrors();

    $exam = Exam::query()->first();

    expect($exam)->not->toBeNull()
        ->and($exam->section_id)->toBe($this->section->id)
        ->and((float) $exam->max_score)->toBe(50.0);
});

it('refuses to create an exam for someone else\'s section', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('newExamSectionId', $this->foreignSection->id)
        ->set('newExamName', 'اختبار')
        ->set('newExamDate', '2026-09-10')
        ->set('newExamMaxScore', '50')
        ->call('createExam')
        ->assertHasErrors('newExamSectionId');

    expect(Exam::query()->count())->toBe(0);
});

it('saves grades entered from the trainer portal', function () {
    $exam = Exam::create([
        'section_id' => $this->section->id,
        'name' => 'اختبار',
        'date' => '2026-09-10',
        'max_score' => 100,
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('openExamGrades', $exam->id)
        ->set('gradeInputs.'.$this->student->id, '87.5')
        ->set('gradeNotes.'.$this->student->id, 'ممتاز')
        ->call('saveGrades')
        ->assertHasNoErrors();

    $grade = ExamGrade::query()->first();

    expect((float) $grade->score)->toBe(87.5)
        ->and($grade->note)->toBe('ممتاز');
});

it('rejects a score above the exam maximum', function () {
    $exam = Exam::create([
        'section_id' => $this->section->id,
        'name' => 'اختبار',
        'date' => '2026-09-10',
        'max_score' => 20,
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('openExamGrades', $exam->id)
        ->set('gradeInputs.'.$this->student->id, '25')
        ->call('saveGrades')
        ->assertHasErrors('gradeInputs.'.$this->student->id);

    expect(ExamGrade::query()->count())->toBe(0);
});

it('only shows the grade to the student once the trainer publishes it', function () {
    $exam = Exam::create([
        'section_id' => $this->section->id,
        'name' => 'اختبار',
        'date' => '2026-09-10',
        'max_score' => 100,
    ]);

    ExamGrade::create(['exam_id' => $exam->id, 'student_id' => $this->student->id, 'score' => 90]);

    Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->assertViewHas('grades', fn ($grades) => $grades->isEmpty());

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('togglePublishGrades', $exam->id);

    expect($exam->fresh()->isGradesPublished())->toBeTrue();

    Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->assertViewHas('grades', fn ($grades) => $grades->count() === 1);
});

it('cannot publish an exam belonging to another trainer', function () {
    $exam = Exam::create([
        'section_id' => $this->foreignSection->id,
        'name' => 'اختبار',
        'date' => '2026-09-10',
        'max_score' => 100,
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('togglePublishGrades', $exam->id);

    expect($exam->fresh()->isGradesPublished())->toBeFalse();
});

it('renders the exams tab and the grade sheet without errors', function () {
    $exam = Exam::create([
        'section_id' => $this->section->id,
        'name' => 'اختبار العرض',
        'date' => '2026-09-10',
        'max_score' => 100,
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('activeTab', 'exams')
        ->assertOk()
        ->assertSee(__('Exams & Grades'))
        ->assertSee(__('New Exam'))
        ->assertSee('اختبار العرض')
        ->call('openExamGrades', $exam->id)
        ->assertOk()
        ->assertSee(__('Save Grades'));
});
