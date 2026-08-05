<?php

use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use Bavix\Wallet\Models\Transaction;

it('names the paying student and the course in the trainer wallet description', function () {
    $subject = Subject::create(['name' => ['en' => 'Photoshop', 'ar' => 'فوتوشوب']]);
    $trainer = Trainer::create([
        'name' => ['en' => 'Yaser', 'ar' => 'ياسر'],
        'username' => 'payout_trn_'.uniqid(),
        'default_rate' => 40,
    ]);
    $section = Section::create([
        'name' => '1',
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => 300,
        'trainer_rate' => 40,
    ]);

    $student = Student::create([
        'name' => ['en' => 'Sami', 'ar' => 'سامي'],
        'username' => 'payout_st_'.uniqid(),
    ]);
    $student->depositFloat(300, ['description' => 'top up']);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 300,
        'amount_paid' => 120,
        'trainer_amount' => 48,
    ]);

    $description = Transaction::query()
        ->where('payable_id', $trainer->id)
        ->latest('id')
        ->value('meta')['description'];

    // The trainer portal only surfaces the description column, so it has to
    // answer "who paid" and "for which course/section" on its own.
    expect($description)
        ->toContain('سامي')
        ->toContain('فوتوشوب')
        ->toContain('1');
});
