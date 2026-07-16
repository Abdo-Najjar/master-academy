<?php

namespace App\Livewire;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class StudentAssignmentSubmission extends Component
{
    use WithFileUploads;

    public Assignment $assignment;

    public ?AssignmentSubmission $submission = null;

    public string $content = '';

    public TemporaryUploadedFile|UploadedFile|null $file = null;

    public function mount(Assignment $assignment): void
    {
        $student = Auth::guard('student')->user();
        $sectionIds = $student?->registrations()->pluck('section_id') ?? collect();

        abort_unless($student && $sectionIds->contains($assignment->section_id), 403);

        $this->assignment = $assignment->load('section.subject');

        $this->submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        $this->content = $this->submission?->content ?? '';
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'content' => __('Content'),
            'file' => __('File'),
        ];
    }

    public function submit(): void
    {
        $student = Auth::guard('student')->user();
        $sectionIds = $student?->registrations()->pluck('section_id') ?? collect();

        abort_unless($student && $sectionIds->contains($this->assignment->section_id), 403);

        $this->validate([
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        $this->submission = AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $this->assignment->id, 'student_id' => $student->id],
            ['content' => $this->content ?: null, 'submitted_at' => now()]
        );

        if ($this->file) {
            $this->submission->addMedia($this->file->getRealPath())
                ->usingFileName($this->file->getClientOriginalName())
                ->toMediaCollection('attachment');
        }

        $this->reset('file');
        $this->submission->refresh();
        session()->flash('message', __('Assignment submitted successfully'));
    }

    public function render()
    {
        return view('livewire.student-assignment-submission');
    }
}
