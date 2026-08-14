<?php

use App\Livewire\StudentDashboard;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\WhatsappCampaign;
use App\Services\WhatsappCampaignService;
use App\Services\WhatsAppService;
use App\Support\AuditReason;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب', 'en' => 'Trainer'],
        'username' => 'audit_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->otherTrainer = Trainer::create([
        'name' => ['ar' => 'مدرب ٢', 'en' => 'Trainer 2'],
        'username' => 'audit_trainer2_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة أ', 'en' => 'Subject A']]);
    $this->otherSubject = Subject::create(['name' => ['ar' => 'مادة ب', 'en' => 'Subject B']]);

    $this->section = Section::create([
        'name' => 'شعبة أ',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
    ]);

    $this->otherSection = Section::create([
        'name' => 'شعبة ب',
        'subject_id' => $this->otherSubject->id,
        'trainer_id' => $this->otherTrainer->id,
        'price' => 100,
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب أ', 'en' => 'Student A'],
        'username' => 'audit_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
        'phone_number' => '0599111222',
    ]);

    $this->otherStudent = Student::create([
        'name' => ['ar' => 'طالب ب', 'en' => 'Student B'],
        'username' => 'audit_student2_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
        'phone_number' => '0599555666',
    ]);

    Registration::create(['student_id' => $this->student->id, 'section_id' => $this->section->id]);
    Registration::create(['student_id' => $this->otherStudent->id, 'section_id' => $this->otherSection->id]);
});

it('stores the stated reason on the activity log entry', function () {
    AuditReason::using('تصحيح خطأ إدخال', function () {
        $this->student->update(['school' => 'مدرسة النجاح']);
    });

    AuditReason::forget();

    $activity = Activity::query()
        ->where('subject_type', Student::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['reason'] ?? null)->toBe('تصحيح خطأ إدخال')
        ->and($activity->properties['attributes']['school'] ?? null)->toBe('مدرسة النجاح');
});

it('leaves the reason off entries logged without one', function () {
    AuditReason::forget();

    $this->student->update(['grade_level' => 'الثاني عشر']);

    $activity = Activity::query()
        ->where('subject_type', Student::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity->properties['reason'] ?? null)->toBeNull();
});

it('targets campaign recipients by section, course, trainer or everyone', function () {
    $byTarget = function (string $type, ?int $id) {
        $campaign = WhatsappCampaign::create([
            'name' => 'حملة '.$type,
            'message' => 'رسالة',
            'target_type' => $type,
            'target_id' => $id,
        ]);

        return WhatsappCampaignService::resolveStudents($campaign)->pluck('id')->sort()->values()->all();
    };

    expect($byTarget(WhatsappCampaign::TARGET_SECTION, $this->section->id))->toBe([$this->student->id])
        ->and($byTarget(WhatsappCampaign::TARGET_SUBJECT, $this->otherSubject->id))->toBe([$this->otherStudent->id])
        ->and($byTarget(WhatsappCampaign::TARGET_TRAINER, $this->trainer->id))->toBe([$this->student->id])
        ->and($byTarget(WhatsappCampaign::TARGET_ALL, null))
        ->toBe(collect([$this->student->id, $this->otherStudent->id])->sort()->values()->all());
});

it('still resolves a saved student group', function () {
    $group = StudentGroup::create(['name' => 'مجموعة تجريبية']);
    $group->students()->attach($this->otherStudent->id);

    $campaign = WhatsappCampaign::create([
        'name' => 'حملة المجموعة',
        'message' => 'رسالة',
        'target_type' => WhatsappCampaign::TARGET_GROUP,
        'student_group_id' => $group->id,
    ]);

    expect(WhatsappCampaignService::resolveStudents($campaign)->pluck('id')->all())
        ->toBe([$this->otherStudent->id]);
});

it('builds recipients for a trainer-wide campaign', function () {
    $campaign = WhatsappCampaign::create([
        'name' => 'حملة المدرب',
        'message' => 'رسالة',
        'target_type' => WhatsappCampaign::TARGET_TRAINER,
        'target_id' => $this->trainer->id,
    ]);

    expect(WhatsappCampaignService::buildRecipients($campaign))->toBe(1)
        ->and($campaign->fresh()->total_count)->toBe(1);
});

it('shows the student their own attendance history', function () {
    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'date' => '2026-09-01',
        'status' => 'absent',
    ]);

    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'date' => '2026-09-03',
        'status' => 'present',
    ]);

    Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->assertViewHas('attendanceBySection', function ($groups) {
            $group = $groups->first();

            return $groups->count() === 1
                && $group['total'] === 2
                && $group['present'] === 1
                && $group['absent'] === 1
                && $group['rate'] === 50.0;
        });
});

it('lists only the student own number in a section contact list', function () {
    $contacts = WhatsAppService::sectionContacts($this->section, 'مرحبا');

    expect(collect($contacts)->pluck('type')->all())->toBe(['student'])
        ->and(collect($contacts)->first()['phone'])->toContain('599111222');
});

it('renders the student attendance tab without errors', function () {
    Attendance::create([
        'section_id' => $this->section->id,
        'student_id' => $this->student->id,
        'date' => '2026-09-01',
        'status' => 'excused',
    ]);

    Livewire::actingAs($this->student, 'student')
        ->test(StudentDashboard::class)
        ->set('activeTab', 'attendance')
        ->assertOk()
        ->assertSee(__('Attendance Rate'))
        ->assertSee(__('Excused'));
});
