<?php

use App\Filament\Admin\Pages\QuickEnroll;
use App\Filament\Admin\Resources\Sections\Pages\ViewSection;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Enrolment from the section's own end.
 *
 * The desk fills a section by opening it and working down a queue, so both
 * doors — a student the centre already has, and one being registered on the
 * spot — have to be on the section page and have to enforce the same rules the
 * student-side screen does. A door that skips the capacity or clash check is
 * worse than no door at all.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'doors-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $admin = User::create([
        'name' => 'Desk Admin',
        'email' => 'doors-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'doors-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $admin->assignRole($role);

    $this->actingAs($admin);

    $this->trainer = Trainer::create([
        'name' => ['en' => 'Door Trainer', 'ar' => 'أستاذ'],
        'username' => 'door_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة التسجيل',
        'subject_id' => Subject::create(['name' => ['en' => 'Subject', 'ar' => 'مادة']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 200,
        'trainer_rate' => 40,
    ]);
});

/** A student on the centre's roll, holding `$balance` before anything is charged. */
function deskStudent(float $balance = 0): Student
{
    $student = Student::create([
        'name' => ['en' => 'Door Student', 'ar' => 'طالب'],
        'username' => 'door_s_'.uniqid(),
        'password' => 'password',
    ]);

    if ($balance > 0) {
        $student->depositFloat($balance, ['description' => 'Opening balance']);
    }

    return $student;
}

it('offers both enrolment doors on the section page', function () {
    $page = Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->assertSuccessful();

    $page->assertActionVisible('enrollExistingStudent')
        ->assertActionVisible('enrollNewStudent');
});

it('enrolls a student the centre already has, from the section page', function () {
    $student = deskStudent(200);

    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => $student->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
        ])
        ->assertHasNoActionErrors();

    $registration = Registration::where('student_id', $student->id)->firstOrFail();

    expect($registration->section_id)->toBe($this->section->id)
        ->and((float) $registration->amount_paid)->toBe(200.0)
        // Paid up front, so the charge is fully funded and the trainer's 40%
        // is credited rather than left waiting behind a debt.
        ->and((float) $registration->funded_amount)->toBe(200.0)
        ->and((float) $registration->trainer_credited_amount)->toBe(80.0)
        ->and((float) $student->fresh()->balanceFloat)->toBe(0.0);
});

it('takes the payment handed over before it charges the enrolment', function () {
    $student = deskStudent();

    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => $student->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
            'payment_amount' => 200,
            'payment_type_id' => PaymentType::create(['name' => ['en' => 'Cash', 'ar' => 'نقداً']])->id,
            'payment_date' => now(),
        ])
        ->assertHasNoActionErrors();

    $registration = Registration::where('student_id', $student->id)->firstOrFail();

    // Deposited first, so the registration reads as funded instead of leaving
    // the student owing what they just handed over.
    expect((float) $registration->funded_amount)->toBe(200.0)
        ->and((float) $student->fresh()->balanceFloat)->toBe(0.0);
});

it('applies an exemption to what the enrolment charges', function () {
    $student = deskStudent(150);

    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => $student->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 50,
            'amount_paid' => 150,
        ])
        ->assertHasNoActionErrors();

    $registration = Registration::where('student_id', $student->id)->firstOrFail();

    expect((float) $registration->exemption_amount)->toBe(50.0)
        ->and((float) $registration->amount_paid)->toBe(150.0);
});

it('refuses to enrol past the section capacity', function () {
    $this->section->update(['capacity' => 1]);

    Registration::create([
        'student_id' => deskStudent(200)->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $latecomer = deskStudent(200);

    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => $latecomer->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
        ])
        ->assertHasActionErrors(['student_id']);

    expect(Registration::where('student_id', $latecomer->id)->exists())->toBeFalse();
});

it('refuses to enrol a student whose other section runs at the same hour', function () {
    // A different trainer: two sections at the same hour is exactly what the
    // trainer double-booking rule exists to stop, and this test is about the
    // *student's* evening being taken, not the trainer's.
    $other = Section::create([
        'name' => 'شعبة متعارضة',
        'subject_id' => Subject::create(['name' => ['en' => 'Other', 'ar' => 'أخرى']])->id,
        'trainer_id' => Trainer::create([
            'name' => ['en' => 'Clash Trainer', 'ar' => 'أستاذ آخر'],
            'username' => 'door_t2_'.uniqid(),
            'password' => 'password',
            'default_rate' => 40,
        ])->id,
        'price' => 100,
    ]);

    foreach ([$this->section, $other] as $section) {
        SectionTime::create([
            'section_id' => $section->id,
            'day' => 'sunday',
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);
    }

    $student = deskStudent(300);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $other->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => $student->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
        ])
        ->assertHasActionErrors(['student_id']);
});

it('refuses to enrol into a section that has no trainer', function () {
    $orphan = Section::create([
        'name' => 'شعبة بلا أستاذ',
        'subject_id' => Subject::create(['name' => ['en' => 'Orphan', 'ar' => 'يتيمة']])->id,
        'price' => 200,
    ]);

    Livewire::test(ViewSection::class, ['record' => $orphan->getKey()])
        ->callAction('enrollExistingStudent', [
            'student_id' => deskStudent(200)->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
        ])
        ->assertHasActionErrors(['student_id']);
});

it('opens quick enroll with the section already picked', function () {
    $this->get(QuickEnroll::getUrl(['section' => $this->section->getKey()]))
        ->assertSuccessful()
        ->assertSee($this->section->name);
});

it('leaves quick enroll empty when it is opened from the menu', function () {
    $this->get(QuickEnroll::getUrl())
        ->assertSuccessful()
        ->assertDontSee($this->section->name);
});
