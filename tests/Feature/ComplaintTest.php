<?php

use App\Models\Complaint;
use App\Models\Student;
use App\Models\User;
use App\Services\ComplaintAlertService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->student = Student::create([
        'name' => ['en' => 'Test Student', 'ar' => 'طالب اختبار'],
        'username' => 'teststudent_'.uniqid(),
        'password' => 'password',
    ]);
});

it('lets a student create a complaint', function () {
    $complaint = $this->student->complaints()->create([
        'subject' => 'Late refund',
        'body' => 'My refund has not arrived yet for the cancelled section.',
        'status' => Complaint::STATUS_OPEN,
    ]);

    expect($complaint->id)->not->toBeNull();
    expect($complaint->status)->toBe('open');
    expect($complaint->complainable_id)->toBe($this->student->id);
    expect($complaint->complainable_type)->toBe(Student::class);
});

it('exposes complaints via the morph relation', function () {
    foreach (range(1, 3) as $i) {
        $this->student->complaints()->create([
            'subject' => "Complaint #$i",
            'body' => 'Body text long enough.',
            'status' => Complaint::STATUS_OPEN,
        ]);
    }

    expect($this->student->complaints()->count())->toBe(3);
});

it('has the expected status colors', function () {
    $open = new Complaint(['status' => Complaint::STATUS_OPEN]);
    $resolved = new Complaint(['status' => Complaint::STATUS_RESOLVED]);
    $inProgress = new Complaint(['status' => Complaint::STATUS_IN_PROGRESS]);

    expect($open->status_color)->toBe('warning');
    expect($inProgress->status_color)->toBe('info');
    expect($resolved->status_color)->toBe('success');
});

it('exposes status labels through the static helper', function () {
    expect(Complaint::statuses())->toHaveKeys([
        Complaint::STATUS_OPEN,
        Complaint::STATUS_IN_PROGRESS,
        Complaint::STATUS_RESOLVED,
    ]);
});

it('notifies only admins with the complaint.index permission of a new complaint', function () {
    Permission::firstOrCreate(['name' => 'complaint.index', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'Complaint Handler', 'guard_name' => 'web']);
    $role->syncPermissions(['complaint.index']);

    $allowedAdmin = User::create([
        'name' => 'Allowed Admin',
        'email' => 'allowed_'.uniqid().'@ma.test',
        'password' => 'password',
    ]);
    $allowedAdmin->assignRole($role);

    $otherAdmin = User::create([
        'name' => 'Other Admin',
        'email' => 'other_'.uniqid().'@ma.test',
        'password' => 'password',
    ]);

    $complaint = $this->student->complaints()->create([
        'subject' => 'Late refund',
        'body' => 'My refund has not arrived yet for the cancelled section.',
        'status' => Complaint::STATUS_OPEN,
    ]);

    app(ComplaintAlertService::class)->notifyNewComplaint($complaint);

    expect($allowedAdmin->notifications()->count())->toBe(1);
    expect($otherAdmin->notifications()->count())->toBe(0);
});

it('never archives open or in-progress complaints regardless of age', function () {
    $this->student->complaints()->create([
        'subject' => 'Old open complaint',
        'body' => 'Body text long enough.',
        'status' => Complaint::STATUS_OPEN,
        'created_at' => now()->subMonths(3),
    ]);

    expect($this->student->complaints()->notArchived()->count())->toBe(1);
});

it('keeps a resolved complaint visible until a week after it was resolved', function () {
    $recentlyResolved = $this->student->complaints()->create([
        'subject' => 'Recently resolved',
        'body' => 'Body text long enough.',
        'status' => Complaint::STATUS_RESOLVED,
        'resolved_at' => now()->subDays(3),
    ]);

    $longResolved = $this->student->complaints()->create([
        'subject' => 'Resolved a while ago',
        'body' => 'Body text long enough.',
        'status' => Complaint::STATUS_RESOLVED,
        'resolved_at' => now()->subDays(8),
    ]);

    $visible = $this->student->complaints()->notArchived()->pluck('id');

    expect($visible)->toContain($recentlyResolved->id);
    expect($visible)->not->toContain($longResolved->id);
});
