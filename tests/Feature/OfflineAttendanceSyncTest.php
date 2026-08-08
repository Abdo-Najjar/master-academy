<?php

use App\Livewire\TrainerDashboard;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب الأوفلاين', 'en' => 'Offline Trainer'],
        'username' => 'offline_trainer_'.uniqid(),
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->otherTrainer = Trainer::create([
        'name' => ['ar' => 'مدرب آخر', 'en' => 'Other Trainer'],
        'username' => 'offline_other_'.uniqid(),
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    $this->section = Section::create([
        'name' => 'شعبة الأوفلاين',
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
        'username' => 'offline_student_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);
});

it('writes queued offline sheets and stamps who recorded them', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('syncOfflineAttendance', [[
            'section_id' => $this->section->id,
            'date' => '2026-09-01',
            'statuses' => [$this->student->id => 'absent'],
            'notes' => [$this->student->id => 'مرض'],
        ]]);

    $row = Attendance::query()->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('absent')
        ->and($row->note)->toBe('مرض')
        ->and($row->recorded_by_type)->toBe(Trainer::class)
        ->and($row->recorded_by_id)->toBe($this->trainer->id)
        ->and($row->recorded_at)->not->toBeNull();
});

it('creates the matching session so the lesson counts as held', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('syncOfflineAttendance', [[
            'section_id' => $this->section->id,
            'date' => '2026-09-01',
            'statuses' => [$this->student->id => 'present'],
        ]]);

    $session = SectionSession::query()->where('section_id', $this->section->id)->first();

    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(SectionSession::STATUS_HELD)
        ->and(Attendance::query()->first()->section_session_id)->toBe($session->id);
});

it('rejects sheets for sections the trainer does not teach', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('syncOfflineAttendance', [[
            'section_id' => $this->foreignSection->id,
            'date' => '2026-09-01',
            'statuses' => [$this->student->id => 'present'],
        ]]);

    expect(Attendance::query()->count())->toBe(0);
});

it('rejects malformed dates and unknown statuses', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('syncOfflineAttendance', [
            [
                'section_id' => $this->section->id,
                'date' => 'not-a-date',
                'statuses' => [$this->student->id => 'present'],
            ],
            [
                'section_id' => $this->section->id,
                'date' => '2026-09-02',
                'statuses' => [$this->student->id => 'teleported'],
            ],
        ]);

    expect(Attendance::query()->count())->toBe(0);
});

it('records the audit reason it was synced offline', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('syncOfflineAttendance', [[
            'section_id' => $this->section->id,
            'date' => '2026-09-01',
            'statuses' => [$this->student->id => 'present'],
        ]]);

    $activity = Activity::query()
        ->where('subject_type', Attendance::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['reason'] ?? null)->toBe(__('Synced from offline attendance'));
});
