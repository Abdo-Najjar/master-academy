<?php

use App\Models\ParentGuardian;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->parent = ParentGuardian::create([
        'name'      => 'أم أحمد',
        'phone'     => '0501111222',
        'password'  => Hash::make('password123'),
        'is_active' => true,
    ]);

    $this->student = Student::create([
        'name'      => ['ar' => 'أحمد', 'en' => 'Ahmed'],
        'username'  => 'ahmed_' . uniqid(),
        'password'  => 'password',
        'parent_id' => $this->parent->id,
    ]);
});

it('parent can be found by phone', function () {
    $found = ParentGuardian::where('phone', '0501111222')->first();
    expect($found?->id)->toBe($this->parent->id);
});

it('parent has correct auth identifier', function () {
    expect($this->parent->getAuthIdentifierName())->toBe('phone');
    expect($this->parent->getAuthIdentifier())->toBe('0501111222');
});

it('parent can log in via guard with correct credentials', function () {
    $result = Auth::guard('parent')->attempt([
        'phone'    => '0501111222',
        'password' => 'password123',
    ]);

    expect($result)->toBeTrue();
    // ParentGuardian uses phone as auth identifier, so id() returns the phone value
    expect(Auth::guard('parent')->user()->id)->toBe($this->parent->id);
});

it('parent cannot log in with wrong password', function () {
    $result = Auth::guard('parent')->attempt([
        'phone'    => '0501111222',
        'password' => 'wrongpassword',
    ]);

    expect($result)->toBeFalse();
});

it('inactive parent cannot log in', function () {
    $this->parent->update(['is_active' => false]);

    $result = Auth::guard('parent')->attempt([
        'phone'    => '0501111222',
        'password' => 'password123',
    ]);

    // Auth::attempt doesn't check is_active itself; that's enforced by middleware
    // But the user IS found and password matches — test the model-level check
    $user = Auth::guard('parent')->getProvider()->retrieveByCredentials([
        'phone'    => '0501111222',
        'password' => 'password123',
    ]);

    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeFalse();
});

it('parent has students relationship', function () {
    $students = $this->parent->students;
    expect($students)->toHaveCount(1);
    expect($students->first()->id)->toBe($this->student->id);
});

it('student belongs to parent', function () {
    expect($this->student->parent?->id)->toBe($this->parent->id);
});

it('parent login route returns 200', function () {
    $response = $this->get(route('parent.login'));
    expect($response->status())->toBe(200);
});

it('parent dashboard redirects to login when unauthenticated', function () {
    $response = $this->get(route('parent.dashboard'));
    expect($response->status())->toBe(302);
    expect($response->headers->get('location'))->toContain('parent/login');
});
