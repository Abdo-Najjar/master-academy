<?php

use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\WhatsAppService;

beforeEach(function () {
    $trainer = Trainer::create([
        'name'     => ['en' => 'WA Trainer', 'ar' => 'مدرب'],
        'username' => 'wa_trainer_' . uniqid(),
        'password' => 'password',
    ]);

    $subject = Subject::create(['name' => ['en' => 'Maths', 'ar' => 'رياضيات']]);

    $this->section = Section::create([
        'name'         => ['ar' => 'قسم الرياضيات', 'en' => 'Math Section'],
        'subject_id'   => $subject->id,
        'trainer_id'   => $trainer->id,
        'price'        => 100,
        'trainer_rate' => 40,
    ]);

    $this->student = Student::create([
        'name'     => ['ar' => 'أحمد علي', 'en' => 'Ahmed Ali'],
        'username' => 'wa_stu_' . uniqid(),
        'password' => 'password',
    ]);
});

it('cancelSessionMessage contains section name and date', function () {
    $msg = WhatsAppService::cancelSessionMessage($this->section, '2026-06-15');

    expect($msg)->toContain('قسم الرياضيات');
    expect($msg)->toContain('2026-06-15');
});

it('rescheduleMessage contains both dates and section name', function () {
    $msg = WhatsAppService::rescheduleMessage($this->section, '2026-06-10', '2026-06-17');

    expect($msg)->toContain('2026-06-10');
    expect($msg)->toContain('2026-06-17');
    expect($msg)->toContain('قسم الرياضيات');
});

it('paymentDueMessage contains student name, section, and amount', function () {
    $msg = WhatsAppService::paymentDueMessage($this->student, 'الرياضيات', 150.0);

    expect($msg)->toContain('أحمد علي');
    expect($msg)->toContain('الرياضيات');
    expect($msg)->toContain('150');
});

it('absenceMessage contains student and section names and date', function () {
    $msg = WhatsAppService::absenceMessage($this->student, $this->section, '2026-06-11');

    expect($msg)->toContain('أحمد علي');
    expect($msg)->toContain('قسم الرياضيات');
    expect($msg)->toContain('2026-06-11');
});

it('send() returns true in test environment (exec is skipped)', function () {
    expect(WhatsAppService::send('0501234567', 'test message'))->toBeTrue();
});
