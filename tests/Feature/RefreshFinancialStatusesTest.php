<?php

use App\Models\Attendance;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;

beforeEach(function () {
    $trainer = Trainer::create([
        'name'     => ['en' => 'Cmd Trainer', 'ar' => 'مدرب'],
        'username' => 'cmd_trainer_' . uniqid(),
        'password' => 'password',
    ]);

    $subject = Subject::create(['name' => ['en' => 'Science', 'ar' => 'علوم']]);

    $this->section = Section::create([
        'name'                   => ['en' => 'Cmd Section', 'ar' => 'قسم'],
        'subject_id'             => $subject->id,
        'trainer_id'             => $trainer->id,
        'price'                  => 100,
        'trainer_rate'           => 40,
        'fee_type'               => 'per_session',
        'sessions_per_fee_cycle' => 3,
    ]);

    $this->student = Student::create([
        'name'     => ['en' => 'Cmd Student', 'ar' => 'طالب'],
        'username' => 'cmd_stu_' . uniqid(),
        'password' => 'password',
    ]);

    $pt = PaymentType::create(['name' => 'Cash']);

    $this->reg = Registration::create([
        'student_id'           => $this->student->id,
        'section_id'           => $this->section->id,
        'payment_type_id'      => $pt->id,
        'amount_due'           => 100,
        'amount_paid'          => 100,
        'exemption_amount'     => 0,
        'trainer_amount'       => 40,
        'session_offset'       => 0,
        'paid_through_session' => 0,
        'financial_status'     => 'ok',
    ]);

    // 4 attendances >= cycle of 3 → should become 'due' or 'overdue'
    // Use future dates so they are after the registration's created_at
    for ($i = 0; $i < 4; $i++) {
        Attendance::create([
            'section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'status'     => 'present',
            'date'       => now()->addDays($i)->toDateString(),
        ]);
    }
});

it('artisan finances:refresh updates financial status', function () {
    $this->artisan('finances:refresh')
        ->assertExitCode(0)
        ->expectsOutputToContain('updated');

    $this->reg->refresh();
    expect($this->reg->financial_status)->toBeIn(['due', 'overdue', 'warning']);
});

it('artisan finances:refresh --dry-run does not update', function () {
    $this->artisan('finances:refresh --dry-run')
        ->assertExitCode(0);

    $this->reg->refresh();
    expect($this->reg->financial_status)->toBe('ok');
});

it('artisan finances:refresh reports count of changed records', function () {
    $this->artisan('finances:refresh')
        ->expectsOutputToContain('registration(s)');
});
