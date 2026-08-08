<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function dashboardUser(array $gates): User
{
    // User #1 is the super admin and bypasses Gate::before — burn that id.
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $user = User::create([
        'name' => 'Limited',
        'email' => 'limited-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

test('a students-only user does not see financial or complaint stats on the dashboard', function () {
    $user = dashboardUser(['student.index']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertStatus(200);
    $response->assertSee(__('Active Students'));
    $response->assertDontSee(__('Weekly Revenue'));
    $response->assertDontSee(__('Outstanding from Students'));
    $response->assertDontSee(__('Due Payments'));
    $response->assertDontSee(__('Open Complaints'));
    $response->assertDontSee(__('Sessions Today'));
    $response->assertDontSee(__('Students with Due Payments'));
    $response->assertDontSee(__('Registrations — last 30 days'));
    $response->assertDontSee(__('Attendance breakdown — last 30 days'));
});

test('a full-permission user still sees every dashboard stat', function () {
    $user = dashboardUser(App\Support\PermissionCatalog::allGates());

    $response = $this->actingAs($user)->get('/admin');

    $response->assertStatus(200);
    $response->assertSee(__('Active Students'));
    $response->assertSee(__('Weekly Revenue'));
    $response->assertSee(__('Open Complaints'));
    $response->assertSee(__('Sessions Today'));
});

test('a user with no dashboard-relevant permission sees no stats but the page still loads', function () {
    $user = dashboardUser(['room.index']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertStatus(200);
    $response->assertDontSee(__('Active Students'));
    $response->assertDontSee(__('Weekly Revenue'));
});
