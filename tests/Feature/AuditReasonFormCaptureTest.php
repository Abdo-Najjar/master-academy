<?php

use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditReason;
use App\Support\PermissionCatalog;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (PermissionCatalog::allGates() as $gate) {
        Permission::findOrCreate($gate, 'web');
    }

    $this->admin = User::create([
        'name' => 'مدقق',
        'email' => 'audit_form_'.uniqid().'@ma.test',
        'password' => 'password',
    ]);
    $this->admin->syncPermissions(PermissionCatalog::allGates());

    $this->student = Student::create([
        'name' => ['ar' => 'طالب التدقيق', 'en' => 'Audit Student'],
        'username' => 'audit_form_student_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    AuditReason::forget();
});

it('carries the reason typed on the edit form into the activity log', function () {
    Livewire::actingAs($this->admin)
        ->test(EditStudent::class, ['record' => $this->student->id])
        ->fillForm([
            'school' => 'مدرسة الأمل',
            'audit_reason' => 'تصحيح اسم المدرسة',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()
        ->where('subject_type', Student::class)
        ->where('subject_id', $this->student->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['reason'] ?? null)->toBe('تصحيح اسم المدرسة')
        ->and($activity->properties['attributes']['school'] ?? null)->toBe('مدرسة الأمل');
});

it('never writes the reason onto the record itself', function () {
    Livewire::actingAs($this->admin)
        ->test(EditStudent::class, ['record' => $this->student->id])
        ->fillForm([
            'school' => 'مدرسة النور',
            'audit_reason' => 'سبب ما',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->student->fresh()->getAttributes())->not->toHaveKey('audit_reason');
});
