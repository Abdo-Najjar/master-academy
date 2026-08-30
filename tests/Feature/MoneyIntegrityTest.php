<?php

/**
 * Invariants the money side has to hold whatever route it is driven through:
 * nothing is created or destroyed on a wallet, and the trainer is credited for
 * exactly their percentage of the money the student actually handed over —
 * never more.
 */

use App\Models\Announcement;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SectionWithdrawalService;
use App\Services\SessionBillingService;
use App\Services\TrainerPayoutService;
use App\Services\WhatsAppService;

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ المال', 'en' => 'Money Trainer'],
        'username' => 'money_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    $this->section = Section::create([
        'name' => 'شعبة المال',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 500,
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب المال', 'en' => 'Money Student'],
        'username' => 'money_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
});

it('never credits the trainer more than their share of the money collected', function () {
    $this->student->depositFloat(500);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 500,
        'amount_paid' => 500,
    ]);

    expect((float) $this->trainer->fresh()->balanceFloat)->toBe(250.0);

    // The desk corrects the charge downwards — the student was overcharged and
    // only really owes 200.
    $registration->update(['amount_paid' => 200]);

    $stored = $registration->fresh();

    expect((float) $this->student->fresh()->balanceFloat)->toBe(300.0, 'رصيد الطالب بعد التصحيح')
        // The trainer's share has to follow the charge down to 50% of 200.
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(100.0, 'رصيد الأستاذ بعد التصحيح')
        ->and((float) $stored->trainer_credited_amount)->toBeLessThanOrEqual((float) $stored->trainer_amount);
});

it('raises the trainer share when the charge is corrected upwards', function () {
    $this->student->depositFloat(500);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    expect((float) $this->trainer->fresh()->balanceFloat)->toBe(100.0);

    $registration->update(['amount_paid' => 500]);

    expect((float) $this->student->fresh()->balanceFloat)->toBe(0.0)
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(250.0);
});

it('leaves nothing behind when a registration is cancelled and refunded', function () {
    $this->student->depositFloat(500);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 500,
        'amount_paid' => 500,
    ]);

    $registration->fresh()->deleteWithWalletAdjustments();

    // Exactly back to where the money started.
    expect((float) $this->student->fresh()->balanceFloat)->toBe(500.0)
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(0.0);
});

it('settles the trainer exactly once when the student pays late', function () {
    // Enrolled with an empty wallet: charged in full, trainer gets nothing yet.
    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 500,
        'amount_paid' => 500,
    ]);

    expect((float) $this->trainer->fresh()->balanceFloat)->toBe(0.0);

    // The family pays in two instalments.
    $this->student->depositFloat(200);
    TrainerPayoutService::settleForStudent($this->student->fresh(), 200);

    expect((float) $this->trainer->fresh()->balanceFloat)->toBe(100.0);

    $this->student->depositFloat(300);
    TrainerPayoutService::settleForStudent($this->student->fresh(), 300);

    $stored = $registration->fresh();

    expect((float) $this->trainer->fresh()->balanceFloat)->toBe(250.0, 'حصة الأستاذ بعد الدفعة الثانية')
        ->and((float) $stored->funded_amount)->toBe(500.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(0.0);
});

it('moves no money when a student withdraws from a section', function () {
    $this->student->depositFloat(500);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 500,
        'amount_paid' => 500,
    ]);

    $studentBalance = (float) $this->student->fresh()->balanceFloat;
    $trainerBalance = (float) $this->trainer->fresh()->balanceFloat;

    SectionWithdrawalService::withdraw($registration->fresh(), '2026-09-01', 'انسحب');

    // Withdrawing records a fact; refunding is a separate, deliberate action.
    expect((float) $this->student->fresh()->balanceFloat)->toBe($studentBalance)
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe($trainerBalance);
});

it('keeps the trainer on their rate across many collected cycles', function () {
    $perSession = Section::create([
        'name' => 'شعبة بالحصة',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 100,
    ]);

    $this->student->depositFloat(400);

    $registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $perSession->id,
        'enrolled_at' => '2026-06-01',
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    SessionBillingService::payCycle($registration->fresh(), 3);

    $stored = $registration->fresh();

    expect((float) $stored->amount_paid)->toBe(400.0)
        ->and((float) $stored->trainer_amount)->toBe(200.0)
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(200.0)
        ->and((float) $this->student->fresh()->balanceFloat)->toBe(0.0)
        ->and($stored->paid_through_session)->toBe(32);
});

it('treats a withdrawn student as gone everywhere the section is addressed', function () {
    $stayed = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-06-01',
    ]);

    $leaver = Student::create([
        'name' => ['ar' => 'منسحب', 'en' => 'Leaver'],
        'username' => 'leaver_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
        'phone_number' => '0599000111',
        'whatsapp_number' => '0599000111',
    ]);

    $leaverRegistration = Registration::create([
        'student_id' => $leaver->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-06-01',
    ]);

    SectionWithdrawalService::withdraw($leaverRegistration->fresh(), '2026-07-01', 'انسحب');

    // The seat is free again.
    expect(Registration::query()->where('section_id', $this->section->id)->stillEnrolled()->count())
        ->toBe(1);

    // They are not messaged about a section they left.
    $contacts = WhatsAppService::sectionContacts($this->section->fresh(), 'رسالة');
    expect(collect($contacts)->pluck('phone'))->not->toContain('0599000111');

    // And the section's announcements stop reaching them.
    $announcement = Announcement::create([
        'title' => 'إعلان الشعبة',
        'body' => 'نص الإعلان',
        'all_sections' => false,
    ]);
    $announcement->sections()->attach($this->section->id);

    expect(Announcement::query()->forStudent($leaver->fresh())->count())->toBe(0)
        ->and(Announcement::query()->forStudent($this->student->fresh())->count())->toBe(1)
        ->and($stayed->fresh()->hasLeft())->toBeFalse();
});
