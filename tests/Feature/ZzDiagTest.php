<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('diag', function () {
    User::create(['name' => 'SA', 'email' => 'sa@ma.test', 'password' => Hash::make('x'), 'email_verified_at' => now(), 'is_active' => true]);

    $user = User::create(['name' => 'L', 'email' => 'l@ma.test', 'password' => Hash::make('x'), 'email_verified_at' => now(), 'is_active' => true]);
    foreach (['student.index'] as $g) {
        Permission::firstOrCreate(['name' => $g, 'guard_name' => 'web']);
    }
    $role = Role::create(['name' => 'r', 'guard_name' => 'web']);
    $role->syncPermissions(['student.index']);
    $user->assignRole($role);

    $html = $this->actingAs($user)->get('/admin')->getContent();

    foreach ([
        'Active Students', 'Weekly Revenue', 'Open Complaints', 'Sessions Today',
        'Due Payments', 'Students with Due Payments', 'Outstanding from Students',
    ] as $key) {
        fwrite(STDERR, sprintf("%-28s ar=%-28s present=%s\n", $key, __($key), str_contains($html, __($key)) ? 'YES' : 'no'));
    }
    fwrite(STDERR, 'html length: '.strlen($html).PHP_EOL);
    fwrite(STDERR, 'has wire:snapshot for widget: '.(str_contains($html, 'OverviewStatsWidget') ? 'YES' : 'no').PHP_EOL);
    fwrite(STDERR, 'lazy placeholder count: '.substr_count($html, 'wire:init').PHP_EOL);

    expect(true)->toBeTrue();
});
