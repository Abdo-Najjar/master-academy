<?php

use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
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
 * The deposit screen builds a field per course the student owes on, so the form
 * itself is the thing worth testing: the per-course lines have to render and the
 * amounts typed into them have to come back keyed by registration.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'deposit-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $this->admin = User::create([
        'name' => 'Deposit Admin',
        'email' => 'deposit-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'deposit-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $this->admin->assignRole($role);

    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'dep_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->maths = Section::create([
        'name' => 'شعبة الرياضيات',
        'subject_id' => Subject::create(['name' => ['ar' => 'رياضيات', 'en' => 'Maths']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 200,
        'start_date' => now()->subMonth()->toDateString(),
    ]);

    $this->arabic = Section::create([
        'name' => 'شعبة العربي',
        'subject_id' => Subject::create(['name' => ['ar' => 'عربي', 'en' => 'Arabic']])->id,
        'trainer_id' => $this->trainer->id,
        'price' => 100,
        'start_date' => now()->subMonth()->toDateString(),
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'dep_s_'.uniqid(),
        'password' => 'password',
    ]);

    $this->mathsRegistration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->maths->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);

    $this->arabicRegistration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->arabic->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    $this->actingAs($this->admin);
});

it('shows the student page with a balance line for each course', function () {
    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->assertOk()
        ->assertSee('شعبة الرياضيات')
        ->assertSee('شعبة العربي');
});

it('mounts the deposit form with a field per owed course', function () {
    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->mountAction('deposit')
        ->assertActionMounted('deposit')
        // Prefilled with everything the student owes across both courses.
        ->assertSchemaComponentStateSet('amount', 300.0)
        ->assertSchemaComponentExists('allocations.'.$this->mathsRegistration->id)
        ->assertSchemaComponentExists('allocations.'.$this->arabicRegistration->id);
});

it('splits a deposit across the courses the desk assigned it to', function () {
    Livewire::test(ViewStudent::class, ['record' => $this->student->getKey()])
        ->callAction('deposit', [
            'amount' => 150,
            'allocations' => [
                $this->mathsRegistration->id => 50,
                $this->arabicRegistration->id => 100,
            ],
        ])
        ->assertHasNoActionErrors();

    expect((float) $this->mathsRegistration->fresh()->funded_amount)->toBe(50.0)
        ->and((float) $this->arabicRegistration->fresh()->funded_amount)->toBe(100.0)
        ->and($this->arabicRegistration->fresh()->financial_status)->toBe('ok')
        ->and(round($this->student->fresh()->balanceFloat, 2))->toBe(-150.0);
});
