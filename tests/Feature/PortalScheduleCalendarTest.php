<?php

use App\Livewire\StudentDashboard;
use App\Livewire\TrainerDashboard;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Livewire\Livewire;

/**
 * Both portals show the month calendar, so a student and a trainer can see the
 * dates they have lessons on rather than only which weekdays they are.
 */
beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ التقويم', 'en' => 'Calendar Trainer'],
        'username' => 'portal_cal_t_'.uniqid(),
        'password' => 'password',
    ]);

    $this->section = Section::create([
        'name' => 'شعبة التقويم البوابة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $this->section->id,
        'day' => 'sunday',
        'start_time' => '16:00',
        'end_time' => '17:30',
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب التقويم', 'en' => 'Calendar Student'],
        'username' => 'portal_cal_s_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);
});

it('hands the student calendar the sections they are actually taking', function () {
    $ids = Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->viewData('scheduleSectionIds');

    expect($ids)->toBe([$this->section->id]);
});

it('drops a section the student withdrew from off their calendar', function () {
    Registration::query()->where('student_id', $this->student->id)
        ->first()
        ->update(['left_at' => now()->toDateString()]);

    $ids = Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->viewData('scheduleSectionIds');

    expect($ids)->toBe([]);
});

it('hands the trainer calendar their own sections', function () {
    $ids = Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->viewData('scheduleSectionIds');

    expect($ids)->toContain($this->section->id);
});

it('gives the trainer a schedule tab that renders the calendar', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('setActiveTab', 'schedule')
        ->assertSet('activeTab', 'schedule')
        // The nested calendar renders with the parent, so a broken binding
        // would surface here rather than in front of a trainer.
        ->assertSuccessful()
        ->assertSee($this->section->name);
});

it('renders the calendar on the student schedule tab', function () {
    Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->call('setActiveTab', 'schedule')
        ->assertSet('activeTab', 'schedule')
        ->assertSuccessful()
        ->assertSee($this->section->name);
});
