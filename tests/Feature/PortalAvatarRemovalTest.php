<?php

use App\Livewire\StudentDashboard;
use App\Livewire\TrainerDashboard;
use App\Models\Student;
use App\Models\Trainer;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('lets a trainer remove their profile picture', function () {
    $trainer = Trainer::create(['name' => 'مدرب صورة', 'username' => 'avatar_trainer', 'password' => 'password', 'is_active' => true]);
    $avatar = UploadedFile::fake()->image('avatar.jpg');
    $trainer->addMedia($avatar->getRealPath())
        ->usingFileName('avatar.jpg')
        ->toMediaCollection('main');

    expect($trainer->fresh()->getMedia('main'))->toHaveCount(1);

    Livewire::actingAs($trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->call('removeAvatar');

    expect($trainer->fresh()->getMedia('main'))->toHaveCount(0);
});

it('lets a student remove their profile picture', function () {
    $student = Student::create(['name' => 'طالب صورة', 'student_number' => 'STU-AVATAR-1', 'password' => 'password', 'is_active' => true]);
    $avatar = UploadedFile::fake()->image('avatar.jpg');
    $student->addMedia($avatar->getRealPath())
        ->usingFileName('avatar.jpg')
        ->toMediaCollection('main');

    expect($student->fresh()->getMedia('main'))->toHaveCount(1);

    Livewire::actingAs($student, 'student')
        ->test(StudentDashboard::class)
        ->call('removeAvatar');

    expect($student->fresh()->getMedia('main'))->toHaveCount(0);
});
