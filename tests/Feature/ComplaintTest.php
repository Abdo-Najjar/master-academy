<?php

use App\Models\Complaint;
use App\Models\Student;

beforeEach(function () {
    $this->student = Student::create([
        'name' => ['en' => 'Test Student', 'ar' => 'طالب اختبار'],
        'username' => 'teststudent_'.uniqid(),
        'password' => 'password',
    ]);
});

it('lets a student create a complaint', function () {
    $complaint = $this->student->complaints()->create([
        'subject' => 'Late refund',
        'body' => 'My refund has not arrived yet for the cancelled section.',
        'status' => Complaint::STATUS_OPEN,
    ]);

    expect($complaint->id)->not->toBeNull();
    expect($complaint->status)->toBe('open');
    expect($complaint->complainable_id)->toBe($this->student->id);
    expect($complaint->complainable_type)->toBe(Student::class);
});

it('exposes complaints via the morph relation', function () {
    foreach (range(1, 3) as $i) {
        $this->student->complaints()->create([
            'subject' => "Complaint #$i",
            'body' => 'Body text long enough.',
            'status' => Complaint::STATUS_OPEN,
        ]);
    }

    expect($this->student->complaints()->count())->toBe(3);
});

it('has the expected status colors', function () {
    $open = new Complaint(['status' => Complaint::STATUS_OPEN]);
    $resolved = new Complaint(['status' => Complaint::STATUS_RESOLVED]);
    $inProgress = new Complaint(['status' => Complaint::STATUS_IN_PROGRESS]);

    expect($open->status_color)->toBe('warning');
    expect($inProgress->status_color)->toBe('info');
    expect($resolved->status_color)->toBe('success');
});

it('exposes status labels through the static helper', function () {
    expect(Complaint::statuses())->toHaveKeys([
        Complaint::STATUS_OPEN,
        Complaint::STATUS_IN_PROGRESS,
        Complaint::STATUS_RESOLVED,
    ]);
});
