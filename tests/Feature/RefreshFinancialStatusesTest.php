<?php

use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;

beforeEach(function () {
    $trainer = Trainer::create([
        'name'     => ['en' => 'Cmd Trainer', 'ar' => 'مدرب'],
        'username' => 'cmd_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $subject = Subject::create(['name' => ['en' => 'Science', 'ar' => 'علوم']]);

    $this->section = Section::create([
        'name'       => 'Cmd Section',
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price'      => 100,
    ]);

    $this->student = Student::create([
        'name'     => ['en' => 'Cmd Student', 'ar' => 'طالب'],
        'username' => 'cmd_stu_'.uniqid(),
        'password' => 'password',
    ]);

    $pt = PaymentType::create(['name' => 'Cash']);

    // The registration lands unfunded ("overdue") since the student's wallet
    // was empty. Stamp a stale "ok" directly, bypassing the observer, the
    // same way a manual DB edit or a refund elsewhere could leave it stale —
    // that's what the command reconciles.
    $this->reg = Registration::create([
        'student_id'      => $this->student->id,
        'section_id'      => $this->section->id,
        'payment_type_id' => $pt->id,
        'amount_due'      => 100,
        'amount_paid'     => 100,
        'exemption_amount' => 0,
        'trainer_amount'  => 40,
    ]);

    $this->reg->forceFill(['financial_status' => 'ok'])->saveQuietly();
});

it('artisan finances:refresh updates financial status', function () {
    $this->artisan('finances:refresh')
        ->assertExitCode(0)
        ->expectsOutputToContain('updated');

    $this->reg->refresh();
    expect($this->reg->financial_status)->toBe('overdue');
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
