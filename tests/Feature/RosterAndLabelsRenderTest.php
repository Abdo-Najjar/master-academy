<?php

use App\Filament\Admin\Resources\Sections\Pages\ViewSection;
use App\Filament\Admin\Resources\Sections\RelationManagers\RegistrationsRelationManager;
use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Filament\Admin\Resources\Students\RelationManagers\RegistrationsRelationManager as StudentRegistrationsRelationManager;
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
 * The parts of these two screens that are only true once they render: the
 * roster count in the heading, the 1-based numbering, and the create button
 * being present *and* translated on a view page.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'roster-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $admin = User::create([
        'name' => 'Roster Admin',
        'email' => 'roster-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'roster-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $admin->assignRole($role);

    $this->actingAs($admin);

    $this->section = Section::create([
        'name' => 'شعبة الكشف',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => Trainer::create([
            'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
            'username' => 'roster_t_'.uniqid(),
            'password' => 'password',
        ])->id,
        'price' => 0,
        'min_capacity' => 3,
        'capacity' => 30,
    ]);

    $this->students = collect(range(1, 3))->map(fn (int $i): Student => Student::create([
        'name' => ['ar' => 'طالب رقم '.$i, 'en' => 'Student '.$i],
        'username' => 'roster_s_'.uniqid(),
        'password' => 'password',
    ]));

    foreach ($this->students as $student) {
        Registration::create([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'amount_due' => 0,
            'amount_paid' => 0,
        ]);
    }
});

it('puts the roster count in the section registrations heading', function () {
    Livewire::test(RegistrationsRelationManager::class, [
        'ownerRecord' => $this->section,
        'pageClass' => ViewSection::class,
    ])
        ->assertSuccessful()
        // "3 / 30", not a bare "Registrations".
        ->assertSee(__('Registrations').' — 3 / 30');
});

it('numbers the section roster from one instead of showing registration ids', function () {
    $sectionTable = Livewire::test(RegistrationsRelationManager::class, [
        'ownerRecord' => $this->section,
        'pageClass' => ViewSection::class,
    ])->instance()->getTable();

    $studentTable = Livewire::test(StudentRegistrationsRelationManager::class, [
        'ownerRecord' => $this->students->first(),
        'pageClass' => ViewStudent::class,
    ])->instance()->getTable();

    // Inside a section "#" is a roster position; on the student's own page it
    // is still the registration's id, which is what identifies the record.
    expect(array_key_first($sectionTable->getColumns()))->toBe('index')
        ->and(array_key_first($studentTable->getColumns()))->toBe('id');
});

it('shows the enrolled count on the section page itself', function () {
    Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->assertSuccessful()
        ->assertSee(__('Enrolled'))
        ->assertSee('3 / 30');
});

it('offers a translated create button on the student view page', function () {
    $student = $this->students->first();

    $component = Livewire::test(StudentRegistrationsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])->assertSuccessful();

    $table = $component->instance()->getTable();
    $create = collect($table->getHeaderActions())->first(fn ($action) => $action->getName() === 'create');

    // Read-only would drop the action entirely on a view page, and an
    // untranslated model label leaves "Registration" sitting in the middle of
    // an otherwise Arabic button.
    expect($component->instance()->isReadOnly())->toBeFalse()
        ->and($create)->not->toBeNull()
        ->and($create->isVisible())->toBeTrue()
        ->and($table->getModelLabel())->toBe(__('Registration'))
        ->and($create->getLabel())->not->toContain('Registration');
});
