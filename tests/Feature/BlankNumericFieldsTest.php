<?php

use App\Filament\Admin\Resources\Registrations\Pages\EditRegistration;
use App\Filament\Admin\Resources\Trainers\Pages\EditTrainer;
use App\Models\Registration;
use App\Models\Section;
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
 * Optional number boxes over NOT NULL columns.
 *
 * Clearing one of these — the exemption on a registration, a trainer's default
 * rate — used to send a null into a column that does not take one, and the save
 * died on an integrity constraint instead of storing the zero the empty box
 * plainly meant.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'blank-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $this->admin = User::create([
        'name' => 'Blank Admin',
        'email' => 'blank-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'blank-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $this->admin->assignRole($role);

    $this->actingAs($this->admin);
});

it('stores a cleared exemption as zero instead of failing the save', function () {
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'blank_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $section = Section::create([
        'name' => 'شعبة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $trainer->id,
        'price' => 50,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'blank_s_'.uniqid(),
        'password' => 'password',
    ]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => now()->toDateString(),
        'amount_due' => 50,
        'exemption_amount' => 10,
        'amount_paid' => 40,
    ]);

    Livewire::test(EditRegistration::class, ['record' => $registration->getKey()])
        ->fillForm([
            'amount_due' => 50,
            'exemption_amount' => null,
            'amount_paid' => 50,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $registration->refresh()->exemption_amount)->toBe(0.0)
        ->and((float) $registration->amount_paid)->toBe(50.0);
});

it('stores a cleared trainer default rate as zero', function () {
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'blank_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    Livewire::test(EditTrainer::class, ['record' => $trainer->getKey()])
        ->fillForm(['default_rate' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $trainer->refresh()->default_rate)->toBe(0.0);
});

it('never writes a null into a registration money column', function () {
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'blank_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $section = Section::create([
        'name' => 'شعبة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $trainer->id,
        'price' => 50,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'blank_s_'.uniqid(),
        'password' => 'password',
    ]);

    // Straight through the model, the way an import or a console command would
    // write it — the guard belongs below the forms, not only inside them.
    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => now()->toDateString(),
        'amount_due' => 50,
        'exemption_amount' => null,
        'amount_paid' => 50,
    ]);

    expect((float) $registration->exemption_amount)->toBe(0.0);

    $registration->update(['exemption_amount' => null, 'trainer_amount' => null]);

    expect((float) $registration->refresh()->exemption_amount)->toBe(0.0)
        ->and((float) $registration->trainer_amount)->toBe(0.0);
});
