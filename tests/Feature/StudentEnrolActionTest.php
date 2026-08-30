<?php

use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
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
 * Enrolling from the student's own page: the action in the header menu runs the
 * same rules as the registration form, so nothing can be slipped past by using
 * this door instead.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'enrol-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $this->admin = User::create([
        'name' => 'Enrol Admin',
        'email' => 'enrol-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'enrol-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $this->admin->assignRole($role);

    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'enrol_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة التسجيل',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 200,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'enrol_s_'.uniqid(),
        'password' => 'password',
    ]);

    $this->paymentType = PaymentType::create(['name' => 'Cash '.uniqid()]);

    $this->actingAs($this->admin);
});

it('enrolls the student into a section from the actions menu', function () {
    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $this->section->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 200,
            'exemption_amount' => 0,
            'amount_paid' => 200,
            'payment_amount' => 200,
            'payment_type_id' => $this->paymentType->id,
        ])
        ->assertHasNoActionErrors();

    $registration = Registration::query()->where('student_id', $this->student->id)->first();

    expect($registration)->not->toBeNull()
        ->and((float) $registration->amount_paid)->toBe(200.0)
        // The payment was banked before the charge, so the bill is settled
        // rather than sitting on a wallet that went negative.
        ->and((float) $registration->funded_amount)->toBe(200.0)
        ->and($registration->financial_status)->toBe('ok');
});

it('refuses a section that clashes with one the student is already in', function () {
    SectionTime::create([
        'section_id' => $this->section->id,
        'day' => 'monday',
        'start_time' => '16:00',
        'end_time' => '17:30',
    ]);

    Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $clashing = Section::create([
        'name' => 'شعبة متعارضة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => Trainer::create([
            'name' => ['ar' => 'أستاذ آخر', 'en' => 'Other'],
            'username' => 'enrol_t_'.uniqid(),
            'password' => 'password',
        ])->id,
        'price' => 0,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $clashing->id,
        'day' => 'monday',
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]);

    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $clashing->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 0,
            'exemption_amount' => 0,
            'amount_paid' => 0,
        ])
        ->assertHasActionErrors(['section_id']);

    expect(Registration::query()->where('student_id', $this->student->id)->count())->toBe(1);
});

it('refuses a section with every seat taken', function () {
    $this->section->update(['capacity' => 1]);

    Registration::create([
        'student_id' => Student::create([
            'name' => ['ar' => 'طالب آخر', 'en' => 'Other'],
            'username' => 'enrol_s_'.uniqid(),
            'password' => 'password',
        ])->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $this->section->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 0,
            'exemption_amount' => 0,
            'amount_paid' => 0,
        ])
        ->assertHasActionErrors(['section_id']);

    expect(Registration::query()->where('student_id', $this->student->id)->count())->toBe(0);
});
