<?php

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('it creates every catalog gate that is missing', function () {
    expect(Permission::count())->toBe(0);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::count())->toBe(count(PermissionCatalog::allGates()));
    expect(Permission::where('name', 'student.index')->exists())->toBeTrue();
});

test('it is idempotent when re-run', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $before = Permission::count();

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('0 permission(s) created.')
        ->assertSuccessful();

    expect(Permission::count())->toBe($before);
});

test('dry run reports missing gates without writing', function () {
    $this->artisan('permissions:sync --dry-run')->assertSuccessful();

    expect(Permission::count())->toBe(0);
    expect(Role::count())->toBe(0);
});

test('it reports stale permissions but keeps them unless pruning', function () {
    Permission::create(['name' => 'ghost.gate', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('ghost.gate')
        ->assertSuccessful();

    expect(Permission::where('name', 'ghost.gate')->exists())->toBeTrue();

    $this->artisan('permissions:sync --prune')->assertSuccessful();

    expect(Permission::where('name', 'ghost.gate')->exists())->toBeFalse();
});

test('it grants every gate to the super admin role and assigns it', function () {
    $superAdmin = User::create([
        'name' => 'Super Admin',
        'email' => 'super@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    expect($superAdmin->id)->toBe((int) config('app.super_admin_id', 1));

    $this->artisan('permissions:sync')->assertSuccessful();

    $role = Role::where('name', __('Super Admin'))->first();

    expect($role)->not->toBeNull();
    expect($role->permissions)->toHaveCount(count(PermissionCatalog::allGates()));
    expect($superAdmin->fresh()->hasRole($role))->toBeTrue();
});

test('a newly added catalog gate reaches the super admin role on the next run', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    // Simulate a module being removed from the role's grants (e.g. a role edited
    // before a new module shipped) — the sync must put it back.
    $role = Role::where('name', __('Super Admin'))->first();
    $role->revokePermissionTo('student.index');

    expect($role->fresh()->hasPermissionTo('student.index'))->toBeFalse();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect($role->fresh()->hasPermissionTo('student.index'))->toBeTrue();
});
