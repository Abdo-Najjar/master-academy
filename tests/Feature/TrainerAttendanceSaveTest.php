<?php

use App\Livewire\TrainerDashboard;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = Trainer::create(['name' => 'مدرب الحضور', 'username' => 'attendance_trainer', 'password' => 'password', 'is_active' => true]);
    $this->subject = Subject::create(['name' => 'مادة الحضور']);
    $this->section = Section::create([
        'name' => 'شعبة الحضور',
        'trainer_id' => $this->trainer->id,
        'subject_id' => $this->subject->id,
        'price' => 100,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-14',
    ]);

    SectionTime::create(['section_id' => $this->section->id, 'day' => 'saturday', 'start_time' => '15:30', 'end_time' => '17:00']);

    $this->student = Student::create(['name' => 'طالب الحضور', 'username' => 'attendance_student', 'password' => 'password']);
    Registration::create(['section_id' => $this->section->id, 'student_id' => $this->student->id]);
});

it('confirms the save with a toast the trainer can see from anywhere on the page', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('attendanceSectionId', $this->section->id)
        ->set('attendanceDate', '2026-08-01')
        ->call('loadAttendance')
        ->call('saveAttendance')
        ->assertDispatched('portal-toast', message: __('Attendance saved'), type: 'success');

    expect(Attendance::where('section_id', $this->section->id)->count())->toBe(1);
});

it('updates a day that was already recorded instead of duplicating it', function () {
    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'status' => 'absent',
        'date' => '2026-08-01',
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('attendanceSectionId', $this->section->id)
        ->set('attendanceDate', '2026-08-01')
        ->call('loadAttendance')
        ->call('setStatus', $this->student->id, 'late')
        ->call('saveAttendance')
        ->assertDispatched('portal-toast');

    $rows = Attendance::where('section_id', $this->section->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->status)->toBe('late');
});

it('exposes held and remaining session counts to the view', function () {
    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'date' => '2026-08-01',
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->assertViewHas('scheduleSummaries', function ($summaries) {
            $summary = $summaries[$this->section->id];

            return $summary['held'] === 1
                && $summary['planned'] === 2
                && $summary['remaining'] === 1;
        });
});

it('loads a past session when the trainer picks it from the list', function () {
    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'status' => 'absent',
        'date' => '2026-08-01',
    ]);

    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('goToSession', $this->section->id, '2026-08-01')
        ->assertSet('activeTab', 'attendance')
        ->assertSet('attendanceDate', '2026-08-01')
        ->assertSet("attendanceStatuses.{$this->student->id}", 'absent');
});
