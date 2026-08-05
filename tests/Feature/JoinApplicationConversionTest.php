<?php

use App\Filament\Admin\Resources\JoinApplications\JoinApplicationResource;
use App\Filament\Admin\Resources\JoinApplications\Pages\ManageJoinApplications;
use App\Models\JoinApplication;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()]
    );

    $this->application = JoinApplication::create([
        'full_name' => 'سارة النجار',
        'phone' => '0599111222',
        'age' => 20,
        'gender' => 'female',
        'program_name' => 'دبلوم التصوير',
        'contact_preference' => 'whatsapp',
    ]);
});

it('generates a unique reference for every request', function () {
    $second = JoinApplication::create([
        'full_name' => 'محمود الشاعر',
        'phone' => '0599333444',
        'contact_preference' => 'phone',
    ]);

    expect($this->application->reference)->not->toBe($second->reference)
        ->and($this->application->reference)->toHaveLength(8);
});

it('creates a student from a join request and marks it enrolled', function () {
    Livewire::actingAs($this->admin)
        ->test(ManageJoinApplications::class)
        ->callTableAction('convertToStudent', $this->application);

    $this->application->refresh();
    $student = Student::sole();

    expect($student->name)->toBe('سارة النجار')
        ->and($student->phone_number)->toBe('0599111222')
        ->and($student->whatsapp_number)->toBe('0599111222')
        ->and($student->gender)->toBe('female')
        ->and($student->student_number)->toStartWith('STU-')
        ->and($this->application->student_id)->toBe($student->id)
        ->and($this->application->status)->toBe('enrolled')
        ->and($this->application->handled_by)->toBe($this->admin->id)
        ->and($this->application->handled_at)->not->toBeNull();
});

it('hides the conversion action once a student exists', function () {
    Livewire::actingAs($this->admin)
        ->test(ManageJoinApplications::class)
        ->callTableAction('convertToStudent', $this->application);

    Livewire::actingAs($this->admin)
        ->test(ManageJoinApplications::class)
        ->assertTableActionHidden('convertToStudent', $this->application->refresh());

    expect(Student::count())->toBe(1);
});

it('does not offer creating join requests from the panel', function () {
    expect(JoinApplicationResource::canCreate())->toBeFalse();
});
