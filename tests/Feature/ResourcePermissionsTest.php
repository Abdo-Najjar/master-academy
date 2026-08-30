<?php

use App\Filament\Admin\Resources\Registrations\Actions\WithdrawFromSectionAction;
use App\Filament\Admin\Resources\Rooms\RoomResource;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Filament\Admin\Resources\Trainers\TrainerResource;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** @return list<class-string> every discovered admin resource */
function adminResources(): array
{
    $classes = [];

    foreach (File::directories(app_path('Filament/Admin/Resources')) as $dir) {
        foreach (File::files($dir) as $file) {
            if (! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            $classes[] = 'App\\Filament\\Admin\\Resources\\'
                .basename($dir).'\\'
                .$file->getFilenameWithoutExtension();
        }
    }

    return $classes;
}

function grantOnly(array $gates): User
{
    foreach ($gates as $gate) {
        Permission::findOrCreate($gate, 'web');
    }

    // Id 1 is the super admin and bypasses every gate, so use a later id.
    User::factory()->create();

    $user = User::factory()->create();
    $user->syncPermissions($gates);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('gates create, update and delete on every admin resource', function () {
    $unprotected = [];

    foreach (adminResources() as $resource) {
        if (! in_array(AuthorizesResourceActions::class, class_uses_recursive($resource), true)) {
            $unprotected[] = class_basename($resource);
        }
    }

    expect($unprotected)->toBe([]);
});

it('points every resource at gates that exist in the catalog', function () {
    $catalog = PermissionCatalog::allGates();
    $unknown = [];

    foreach (adminResources() as $resource) {
        $prefix = $resource::permissionPrefix();

        if (! in_array($prefix.'.index', $catalog, true)) {
            $unknown[] = $prefix.'.index';
        }
    }

    expect($unknown)->toBe([]);
});

it('lets a view-only operator read but not create, edit or delete', function () {
    $user = grantOnly(['student.index']);
    $this->actingAs($user);

    $resource = StudentResource::class;
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'perm_'.uniqid(),
        'password' => 'password',
    ]);

    expect($resource::canAccess())->toBeTrue()
        ->and($resource::canViewAny())->toBeTrue()
        ->and($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($student))->toBeFalse()
        ->and($resource::canDelete($student))->toBeFalse()
        ->and($resource::canForceDelete($student))->toBeFalse()
        ->and($resource::canRestore($student))->toBeFalse();
});

it('opens each action up only with its own gate', function () {
    $room = Room::create(['number' => 'P-1']);
    $resource = RoomResource::class;

    $this->actingAs(grantOnly(['room.index', 'room.create']));
    expect($resource::canCreate())->toBeTrue()
        ->and($resource::canEdit($room))->toBeFalse()
        ->and($resource::canDelete($room))->toBeFalse();

    $this->actingAs(grantOnly(['room.index', 'room.update']));
    expect($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($room))->toBeTrue()
        ->and($resource::canDelete($room))->toBeFalse();

    $this->actingAs(grantOnly(['room.index', 'room.delete']));
    expect($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($room))->toBeFalse()
        ->and($resource::canDelete($room))->toBeTrue();
});

it('hides a resource entirely from an operator without its view gate', function () {
    $this->actingAs(grantOnly(['student.index']));

    expect(RoomResource::canAccess())->toBeFalse()
        ->and(TrainerResource::canAccess())->toBeFalse()
        ->and(StudentResource::canAccess())->toBeTrue();
});

it('gates each heavyweight registration action behind its own permission', function () {
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'perm_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);
    $subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);
    $section = Section::create([
        'name' => 'شعبة الصلاحيات',
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => 100,
    ]);
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'perm_student_'.uniqid(),
        'password' => 'password',
    ]);
    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    $withdraw = fn () => WithdrawFromSectionAction::make()
        ->record($registration)
        ->isVisible();

    // Being able to edit a registration is not the same as being able to end it.
    $this->actingAs(grantOnly(['registration.index', 'registration.update']));
    expect($withdraw())->toBeFalse();

    $this->actingAs(grantOnly(['registration.index', 'registration.withdraw']));
    expect($withdraw())->toBeTrue();
});

it('lists every gate the catalog declares, including the newest ones', function () {
    $gates = PermissionCatalog::allGates();

    expect($gates)
        ->toContain('registration.withdraw')
        ->toContain('registration.cancel')
        ->toContain('registration.transfer')
        ->toContain('registration.collect')
        // No duplicates: the roles screen renders one checkbox per gate.
        ->and(array_unique($gates))->toHaveCount(count($gates));
});
