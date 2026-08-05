<?php

use App\Filament\Admin\Pages\ManageSiteSettings;
use App\Filament\Admin\Resources\JoinApplications\JoinApplicationResource;
use App\Filament\Admin\Resources\JoinApplications\Pages\ManageJoinApplications;
use App\Filament\Admin\Resources\Programs\Pages\ManagePrograms;
use App\Filament\Admin\Resources\Programs\ProgramResource;
use App\Filament\Admin\Resources\SiteMedia\SiteMediaResource;
use App\Filament\Admin\Resources\Testimonials\TestimonialResource;
use App\Models\JoinApplication;
use App\Models\Program;
use App\Models\User;
use App\Support\PermissionCatalog;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Grants a fresh non-super-admin user exactly the listed gates. User #1 bypasses
 * every gate via Gate::before, so a second user is required to test permissions.
 */
function userWithPermissions(array $gates): User
{
    if (! User::find(1)) {
        User::factory()->create();
    }

    $user = User::factory()->create();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'Website Editor '.$user->id, 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user->fresh();
}

it('declares every website gate in the permission catalog', function () {
    $gates = PermissionCatalog::allGates();

    expect($gates)->toContain(
        'program.index', 'program.create', 'program.update', 'program.delete',
        'testimonial.index', 'testimonial.create', 'testimonial.update', 'testimonial.delete',
        'site_media.index', 'site_media.create', 'site_media.update', 'site_media.delete',
        'join_application.index', 'join_application.update', 'join_application.delete',
        'site_settings.manage',
    );
});

it('exposes the website modules on the roles screen', function () {
    expect(PermissionCatalog::moduleLabels())
        ->toHaveKeys(['program', 'testimonial', 'site_media', 'join_application', 'site_settings']);
});

it('hides website resources from a user without the view gate', function () {
    $user = userWithPermissions([]);

    $this->actingAs($user);

    expect(ProgramResource::canAccess())->toBeFalse()
        ->and(TestimonialResource::canAccess())->toBeFalse()
        ->and(SiteMediaResource::canAccess())->toBeFalse()
        ->and(JoinApplicationResource::canAccess())->toBeFalse()
        ->and(ManageSiteSettings::canAccess())->toBeFalse();
});

it('allows viewing but not writing with only the view gate', function () {
    $user = userWithPermissions(['program.index']);
    $program = Program::create([
        'title' => ['ar' => 'برنامج', 'en' => 'Program'],
        'category' => 'technical',
    ]);

    $this->actingAs($user);

    expect(ProgramResource::canAccess())->toBeTrue()
        ->and(ProgramResource::canCreate())->toBeFalse()
        ->and(ProgramResource::canEdit($program))->toBeFalse()
        ->and(ProgramResource::canReorder())->toBeFalse()
        ->and(ProgramResource::canDelete($program))->toBeFalse()
        ->and(ProgramResource::canDeleteAny())->toBeFalse()
        ->and(ProgramResource::canRestore($program))->toBeFalse();
});

it('unlocks each write action with its own gate', function () {
    $user = userWithPermissions(['program.index', 'program.create', 'program.update', 'program.delete']);
    $program = Program::create([
        'title' => ['ar' => 'برنامج', 'en' => 'Program'],
        'category' => 'technical',
    ]);

    $this->actingAs($user);

    expect(ProgramResource::canCreate())->toBeTrue()
        ->and(ProgramResource::canEdit($program))->toBeTrue()
        ->and(ProgramResource::canReorder())->toBeTrue()
        ->and(ProgramResource::canDelete($program))->toBeTrue()
        ->and(ProgramResource::canForceDelete($program))->toBeTrue();
});

it('never offers creating join requests, even with every gate', function () {
    $user = userWithPermissions(['join_application.index', 'join_application.update', 'join_application.delete']);

    $this->actingAs($user);

    expect(JoinApplicationResource::canAccess())->toBeTrue()
        ->and(JoinApplicationResource::canCreate())->toBeFalse();
});

it('hides the convert action without permission to create students', function () {
    $application = JoinApplication::create([
        'full_name' => 'سارة النجار',
        'phone' => '0599111222',
        'contact_preference' => 'whatsapp',
    ]);

    $viewer = userWithPermissions(['join_application.index', 'join_application.update']);

    Livewire::actingAs($viewer)
        ->test(ManageJoinApplications::class)
        ->assertTableActionHidden('convertToStudent', $application);
});

it('shows the convert action once both gates are granted', function () {
    $application = JoinApplication::create([
        'full_name' => 'سارة النجار',
        'phone' => '0599111222',
        'contact_preference' => 'whatsapp',
    ]);

    $editor = userWithPermissions(['join_application.index', 'join_application.update', 'student.create']);

    Livewire::actingAs($editor)
        ->test(ManageJoinApplications::class)
        ->assertTableActionVisible('convertToStudent', $application);
});

it('hides every write action on the program table from a read-only user', function () {
    $program = Program::create([
        'title' => ['ar' => 'برنامج', 'en' => 'Program'],
        'category' => 'technical',
    ]);

    Livewire::actingAs(userWithPermissions(['program.index']))
        ->test(ManagePrograms::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('edit', $program)
        ->assertTableActionHidden('delete', $program)
        ->assertTableBulkActionHidden('delete');
});

it('shows the program write actions once the gates are granted', function () {
    $program = Program::create([
        'title' => ['ar' => 'برنامج', 'en' => 'Program'],
        'category' => 'technical',
    ]);

    Livewire::actingAs(userWithPermissions(['program.index', 'program.create', 'program.update', 'program.delete']))
        ->test(ManagePrograms::class)
        ->assertActionVisible('create')
        ->assertTableActionVisible('edit', $program)
        ->assertTableActionVisible('delete', $program)
        ->assertTableBulkActionVisible('delete');
});

it('hides the follow-up actions from a read-only user', function () {
    $application = JoinApplication::create([
        'full_name' => 'سارة النجار',
        'phone' => '0599111222',
        'contact_preference' => 'whatsapp',
    ]);

    $viewer = userWithPermissions(['join_application.index']);

    Livewire::actingAs($viewer)
        ->test(ManageJoinApplications::class)
        ->assertTableActionHidden('whatsapp', $application)
        ->assertTableActionHidden('edit', $application)
        ->assertTableActionHidden('delete', $application);
});

it('gates the site settings page on its own permission', function () {
    $this->actingAs(userWithPermissions(['site_settings.manage']));

    expect(ManageSiteSettings::canAccess())->toBeTrue();

    $this->actingAs(userWithPermissions(['program.index']));

    expect(ManageSiteSettings::canAccess())->toBeFalse();
});
