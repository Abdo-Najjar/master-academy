<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets the User with id 1 pass any gate (super admin)', function () {
    $admin = User::factory()->create();

    expect($admin->getKey())->toBe(1)
        ->and(Gate::forUser($admin)->allows('some.undefined.ability'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('backup.run'))->toBeTrue();
});

it('does not grant blanket access to other users', function () {
    User::factory()->create();            // id 1 (super admin)
    $other = User::factory()->create();   // id 2

    expect($other->getKey())->toBe(2)
        ->and(Gate::forUser($other)->allows('some.undefined.ability'))->toBeFalse();
});

it('does not grant super admin to a non-User model with id 1', function () {
    // Gate::before is scoped to App\Models\User, so a Student #1 must not inherit it.
    $student = App\Models\Student::create([
        'name' => ['ar' => 'ط', 'en' => 'S'],
        'username' => 'sg_'.uniqid(),
    ]);

    expect(Gate::forUser($student)->allows('some.undefined.ability'))->toBeFalse();
});
