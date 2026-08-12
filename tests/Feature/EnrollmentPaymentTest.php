<?php

use App\Filament\Admin\Pages\QuickEnroll;
use App\Filament\Admin\Resources\Registrations\Pages\CreateRegistration;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Filament\Admin\Resources\Students\Tables\StudentsTable;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use Bavix\Wallet\Models\Transaction;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'enroll-admin-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $this->trainer = Trainer::create([
        'name' => ['ar' => 'مدرب', 'en' => 'Trainer'],
        'username' => 'pay_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    $this->section = Section::create([
        'name' => 'شعبة الدفع',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 500,
    ]);

    $this->paymentType = PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']]);
});

it('leaves the wallet square when the full amount is paid at enrollment', function () {
    Livewire::actingAs($this->admin)
        ->test(QuickEnroll::class)
        ->fillForm([
            'name' => ['ar' => 'طالب دافع', 'en' => 'Paying Student'],
            'username' => 'payer_'.uniqid(),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'registrations' => [
                ['section_id' => $this->section->id, 'amount_due' => 500, 'amount_paid' => 500, 'exemption_amount' => 0],
            ],
            'payment_amount' => 500,
            'payment_type_id' => $this->paymentType->id,
        ])
        ->call('save');

    $student = Student::query()->latest('id')->first();
    $registration = Registration::query()->latest('id')->first();

    expect((float) $student->balanceFloat)->toBe(0.0)
        // Fully funded, so the trainer's 50% is credited in full.
        ->and((float) $registration->funded_amount)->toBe(500.0)
        ->and($registration->financial_status)->toBe('ok')
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(250.0);
});

it('still enrolls when nothing is paid, leaving the balance owed', function () {
    Livewire::actingAs($this->admin)
        ->test(QuickEnroll::class)
        ->fillForm([
            'name' => ['ar' => 'طالب غير دافع', 'en' => 'Unpaid Student'],
            'username' => 'unpaid_'.uniqid(),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'registrations' => [
                ['section_id' => $this->section->id, 'amount_due' => 500, 'amount_paid' => 500, 'exemption_amount' => 0],
            ],
            'payment_amount' => 0,
        ])
        ->call('save');

    $student = Student::query()->latest('id')->first();
    $registration = Registration::query()->latest('id')->first();

    expect((float) $student->balanceFloat)->toBe(-500.0)
        ->and((float) $registration->funded_amount)->toBe(0.0)
        ->and($registration->financial_status)->toBe('overdue')
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(0.0);
});

it('records a part payment as partially funded', function () {
    Livewire::actingAs($this->admin)
        ->test(QuickEnroll::class)
        ->fillForm([
            'name' => ['ar' => 'طالب دفع جزئي', 'en' => 'Part Payer'],
            'username' => 'part_'.uniqid(),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'registrations' => [
                ['section_id' => $this->section->id, 'amount_due' => 500, 'amount_paid' => 500, 'exemption_amount' => 0],
            ],
            'payment_amount' => 200,
            'payment_type_id' => $this->paymentType->id,
        ])
        ->call('save');

    $student = Student::query()->latest('id')->first();
    $registration = Registration::query()->latest('id')->first();

    expect((float) $student->balanceFloat)->toBe(-300.0)
        ->and((float) $registration->funded_amount)->toBe(200.0)
        ->and($registration->financial_status)->toBe('due')
        ->and((float) $this->trainer->fresh()->balanceFloat)->toBe(100.0);
});

it('keeps the payment on the wallet statement with its type and note', function () {
    Livewire::actingAs($this->admin)
        ->test(QuickEnroll::class)
        ->fillForm([
            'name' => ['ar' => 'طالب', 'en' => 'Student'],
            'username' => 'stmt_'.uniqid(),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'registrations' => [
                ['section_id' => $this->section->id, 'amount_due' => 500, 'amount_paid' => 500, 'exemption_amount' => 0],
            ],
            'payment_amount' => 500,
            'payment_type_id' => $this->paymentType->id,
            'payment_note' => 'دفع نقداً عند التسجيل',
        ])
        ->call('save');

    $student = Student::query()->latest('id')->first();

    $deposit = Transaction::query()
        ->where('wallet_id', $student->wallet->id)
        ->where('type', 'deposit')
        ->first();

    expect($deposit)->not->toBeNull()
        ->and($deposit->meta['description'])->toBe(__('Payment received at enrollment'))
        ->and($deposit->meta['payment_type_id'])->toBe($this->paymentType->id)
        ->and($deposit->meta['note'])->toBe('دفع نقداً عند التسجيل');
});

it('takes the payment when registering an existing student too', function () {
    $student = Student::create([
        'name' => ['ar' => 'طالب قائم', 'en' => 'Existing'],
        'username' => 'existing_'.uniqid(),
        'password' => 'password',
    ]);

    Livewire::actingAs($this->admin)
        ->test(CreateRegistration::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'amount_due' => 500,
            'exemption_amount' => 0,
            'amount_paid' => 500,
            'payment_amount' => 500,
            'payment_type_id' => $this->paymentType->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $registration = Registration::query()->where('student_id', $student->id)->first();

    expect((float) $student->fresh()->balanceFloat)->toBe(0.0)
        ->and((float) $registration->funded_amount)->toBe(500.0)
        ->and($registration->financial_status)->toBe('ok');
});

it('no longer offers a plain create-student page', function () {
    expect(StudentResource::canCreate())->toBeFalse()
        ->and(array_keys(StudentResource::getPages()))->not->toContain('create');

    $this->actingAs($this->admin)
        ->get('/admin/students/create')
        ->assertNotFound();
});

it('refuses to delete a student who is registered in a course', function () {
    $student = Student::create([
        'name' => ['ar' => 'طالب مسجل', 'en' => 'Enrolled'],
        'username' => 'guard_'.uniqid(),
        'password' => 'password',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $this->section->id,
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    expect(fn () => StudentsTable::guardStudentDeletion($student))
        ->toThrow(Halt::class);

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});

it('allows deleting a student with no registrations', function () {
    $student = Student::create([
        'name' => ['ar' => 'طالب بدون تسجيل', 'en' => 'Free'],
        'username' => 'free_'.uniqid(),
        'password' => 'password',
    ]);

    StudentsTable::guardStudentDeletion($student);

    $student->delete();

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse();
});
