<?php

use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('portal landing page renders', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

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

test('unauthenticated trainer dashboard redirects to login', function () {
    $response = $this->get('/trainer/dashboard');
    $response->assertRedirect('/trainer/login');
});

test('unauthenticated student dashboard redirects to login', function () {
    $response = $this->get('/student/dashboard');
    $response->assertRedirect('/student/login');
});

test('authenticated admin can load the dashboard', function () {
    $admin = User::query()->firstOrCreate(
        ['email' => 'test-admin@ma.test'],
        ['name' => 'Tester', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true]
    );

    $response = $this->actingAs($admin)->get('/admin');
    $response->assertStatus(200);
});

test('authenticated admin with a role can load all resource pages', function () {
    $admin = User::query()->firstOrCreate(
        ['email' => 'test-admin@ma.test'],
        ['name' => 'Tester', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true]
    );

    $gates = [
        'student.index', 'trainer.index', 'subject.index', 'section.index',
        'registration.index', 'room.index', 'governorate.index', 'city.index',
        'payment_type.index', 'user.index', 'attendance.index',
    ];

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'Test Admin', 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $admin->assignRole($role);

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
        '/admin/users',
    ] as $url) {
        $response = $this->actingAs($admin)->get($url);
        expect($response->status())->toBe(200, "Failed loading {$url} - status {$response->status()}");
    }
});

test('authenticated trainer can load dashboard', function () {
    $trainer = Trainer::query()->firstOrCreate(
        ['username' => 'trainer-test'],
        [
            'name' => 'Test Trainer',
            'email' => 'trainer-test@ma.test',
            'password' => Hash::make('password'),
            'default_rate' => 50,
            'is_active' => true,
        ]
    );

    $response = $this->actingAs($trainer, 'trainer')->get('/trainer/dashboard');
    expect($response->status())->toBe(200);
});

test('authenticated student can load dashboard', function () {
    $student = Student::query()->firstOrCreate(
        ['username' => 'student-test'],
        [
            'name' => 'Test Student',
            'email' => 'student-test@ma.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]
    );

    $response = $this->actingAs($student, 'student')->get('/student/dashboard');
    expect($response->status())->toBe(200);
});
