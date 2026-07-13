<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()]
    );
    if (! $this->admin->is_active) {
        $this->admin->update(['is_active' => true]);
    }
});

$pages = [
    '/admin',
    '/admin/students',
    '/admin/trainers',
    '/admin/parents',
    '/admin/subjects',
    '/admin/sections',
    '/admin/rooms',
    '/admin/quick-enroll',
    '/admin/registrations',
    '/admin/take-attendance',
    '/admin/exams',
    '/admin/announcements',
    '/admin/complaints',
    '/admin/payment-types',
    '/admin/reports',
    '/admin/wallet-transactions',
    '/admin/governorates',
    '/admin/cities',
    '/admin/roles',
    '/admin/users',
    '/admin/certificate-templates',
    '/admin/certificates',
    '/admin/whats-app-settings',
    '/admin/manage-app-settings',
    '/admin/login-activities',
];

it('renders admin page without server error: {page}', function (string $page) {
    $response = $this->actingAs($this->admin)->get($page);

    // 200 OK or 3xx redirect are fine; 500 = a real rendering bug.
    expect($response->status())->toBeLessThan(500, "Page {$page} returned {$response->status()}");
})->with($pages);
