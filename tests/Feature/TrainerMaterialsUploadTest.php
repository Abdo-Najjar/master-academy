<?php

use App\Livewire\TrainerDashboard;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Trainer;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = Trainer::create(['name' => 'مدرب مواد', 'username' => 'materials_trainer', 'password' => 'password', 'is_active' => true]);
    $this->subject = Subject::create(['name' => 'مادة مواد']);
    $this->section = Section::create(['name' => 'شعبة مواد', 'trainer_id' => $this->trainer->id, 'subject_id' => $this->subject->id, 'price' => 100]);
});

it('rejects uploading materials when no file was chosen', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('materialsSectionId', $this->section->id)
        ->call('uploadMaterials')
        ->assertHasErrors(['newMaterials']);

    expect($this->section->fresh()->getMedia('materials'))->toHaveCount(0);
});

it('uploads a material file when one is chosen', function () {
    Livewire::actingAs($this->trainer, 'trainer')
        ->test(TrainerDashboard::class)
        ->set('materialsSectionId', $this->section->id)
        ->set('newMaterials', [UploadedFile::fake()->create('lesson.pdf', 100)])
        ->call('uploadMaterials')
        ->assertHasNoErrors();

    expect($this->section->fresh()->getMedia('materials'))->toHaveCount(1);
});
