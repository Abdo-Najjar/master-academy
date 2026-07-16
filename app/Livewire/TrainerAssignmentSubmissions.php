<?php

namespace App\Livewire;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrainerAssignmentSubmissions extends Component
{
    public Assignment $assignment;

    public string $statusFilter = '';

    public string $studentSearch = '';

    /** @var array<int, string|float|null> submission_id => grade */
    public array $gradeInputs = [];

    /** @var array<int, string> submission_id => feedback */
    public array $feedbackInputs = [];

    public function mount(Assignment $assignment): void
    {
        $trainer = Auth::guard('trainer')->user();

        abort_unless($trainer && $assignment->section?->trainer_id === $trainer->id, 403);

        $this->assignment = $assignment->load('section.subject');

        foreach ($assignment->submissions as $submission) {
            $this->gradeInputs[$submission->id] = $submission->grade;
            $this->feedbackInputs[$submission->id] = $submission->feedback ?? '';
        }
    }

    public function saveGrade(int $submissionId): void
    {
        $trainer = Auth::guard('trainer')->user();

        $submission = AssignmentSubmission::query()
            ->whereKey($submissionId)
            ->where('assignment_id', $this->assignment->id)
            ->first();

        if (! $submission || $this->assignment->section?->trainer_id !== $trainer?->id) {
            return;
        }

        $grade = $this->gradeInputs[$submissionId] ?? null;

        $submission->update([
            'grade' => $grade !== '' && $grade !== null ? (float) $grade : null,
            'feedback' => $this->feedbackInputs[$submissionId] ?? null,
        ]);

        session()->flash('message', __('Grade saved'));
    }

    public function render()
    {
        $submissions = $this->assignment->submissions()->with('student')->get()->keyBy('student_id');

        $rows = $this->assignment->section
            ->registrations()
            ->with('student')
            ->get()
            ->map(function ($registration) use ($submissions) {
                $student = $registration->student;
                $submission = $student ? $submissions->get($student->id) : null;

                return [
                    'student' => $student,
                    'submission' => $submission,
                    'status' => $submission
                        ? ($submission->isGraded() ? 'graded' : 'submitted')
                        : 'not_submitted',
                ];
            })
            ->filter(fn (array $row): bool => (bool) $row['student'])
            ->when($this->statusFilter !== '', fn ($rows) => $rows->where('status', $this->statusFilter))
            ->when($this->studentSearch !== '', function ($rows) {
                $needle = mb_strtolower($this->studentSearch);

                return $rows->filter(function (array $row) use ($needle) {
                    $name = $row['student']->getTranslation('name', app()->getLocale(), false) ?? '';

                    return str_contains(mb_strtolower($name), $needle)
                        || str_contains(mb_strtolower((string) $row['student']->student_number), $needle);
                });
            })
            ->sortBy(fn (array $row) => $row['student']->getTranslation('name', app()->getLocale(), false))
            ->values();

        return view('livewire.trainer-assignment-submissions', [
            'rows' => $rows,
        ]);
    }
}
