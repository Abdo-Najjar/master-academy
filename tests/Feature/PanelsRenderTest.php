<?php

use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin login page renders', function () {
    $response = $this->get('/admin/login');
    $response->assertStatus(200);
});

test('trainer login page renders', function () {
    $response = $this->get('/trainer/login');
    $response->assertStatus(200);
});

test('student login page renders', function () {
    $response = $this->get('/student/login');
    $response->assertStatus(200);
});

test('authenticated admin can load the dashboard', function () {
    $admin = User::query()->firstOrCreate(
        ['email' => 'test-admin@ma.test'],
        ['name' => 'Tester', 'password' => Hash::make('password'), 'email_verified_at' => now()]
    );

    $response = $this->actingAs($admin)->get('/admin');
    $response->assertStatus(200);
});

test('authenticated admin can load Students list', function () {
    $admin = User::query()->firstOrCreate(
        ['email' => 'test-admin@ma.test'],
        ['name' => 'Tester', 'password' => Hash::make('password'), 'email_verified_at' => now()]
    );

    if (! $admin->hasRole('admin')) {
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($adminRole);
    }

    $perms = ['view_students', 'view_trainers', 'view_subjects', 'view_sections', 'view_registrations', 'view_rooms', 'view_governorates', 'view_cities', 'view_payment_types', 'view_educational_levels', 'view_users', 'view_attendances'];
    foreach ($perms as $perm) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $admin->givePermissionTo($perms);

    foreach ([
        '/admin/students',
        '/admin/trainers',
        '/admin/subjects',
        '/admin/sections',
        '/admin/registrations',
        '/admin/rooms',
        '/admin/governorates',
        '/admin/cities',
        '/admin/payment-types',
        '/admin/educational-levels',
        '/admin/users',
        '/admin/attendances',
    ] as $url) {
        $response = $this->actingAs($admin)->get($url);
        expect($response->status())->toBe(200, "Failed loading {$url} - status {$response->status()}");
    }
});

test('authenticated trainer can load own sections', function () {
    $trainer = Trainer::query()->firstOrCreate(
        ['username' => 'trainer-test'],
        [
            'name' => 'Test Trainer',
            'email' => 'trainer-test@ma.test',
            'password' => Hash::make('password'),
            'default_rate' => 50,
        ]
    );

    $response = $this->actingAs($trainer, 'trainer')->get('/trainer/sections');
    expect($response->status())->toBe(200);
});

test('authenticated student can load own registrations', function () {
    $student = Student::query()->firstOrCreate(
        ['username' => 'student-test'],
        [
            'name' => 'Test Student',
            'email' => 'student-test@ma.test',
            'password' => Hash::make('password'),
        ]
    );

    $response = $this->actingAs($student, 'student')->get('/student/registrations');
    expect($response->status())->toBe(200);
});
