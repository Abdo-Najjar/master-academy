<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithTrainerAuth;
use App\Livewire\Concerns\NotifiesPortal;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Exam;
use App\Models\ExamGrade;
use App\Models\Section;
use App\Notifications\AssignmentCreated;
use App\Services\ComplaintAlertService;
use App\Services\SectionScheduleService;
use App\Support\AuditReason;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Layout('components.layouts.app')]
class TrainerDashboard extends Component
{
    use InteractsWithTrainerAuth, NotifiesPortal, WithFileUploads;

    /** Mirrored to ?tab= so tabs are linkable and browser back/forward works. */
    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'sections';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public TemporaryUploadedFile|UploadedFile|null $newAvatar = null;

    public ?int $attendanceSectionId = null;

    public string $attendanceDate = '';

    /** @var array<int, string> student_id => status */
    public array $attendanceStatuses = [];

    /** @var array<int, string> student_id => optional note */
    public array $attendanceNotes = [];

    /** Why an already-recorded day is being changed; stored in the audit log. */
    public string $attendanceEditReason = '';

    /** True once the selected day already has attendance rows. */
    public bool $attendanceDayHasRecords = false;

    public ?int $materialsSectionId = null;

    public ?int $newExamSectionId = null;

    public string $newExamName = '';

    public string $newExamDate = '';

    public string $newExamMaxScore = '100';

    public string $newExamNote = '';

    /** Exam whose grade sheet is currently open. */
    public ?int $gradingExamId = null;

    /** @var array<int, string> student_id => score */
    public array $gradeInputs = [];

    /** @var array<int, string> student_id => note */
    public array $gradeNotes = [];

    /** @var array<int, TemporaryUploadedFile|UploadedFile> */
    public array $newMaterials = [];

    public string $complaintSubject = '';

    public string $complaintBody = '';

    public ?int $newAssignmentSectionId = null;

    public string $newAssignmentTitle = '';

    public string $newAssignmentDescription = '';

    public string $newAssignmentDueDate = '';

    /** '' = active sections only, 'all' = include finished, otherwise a section id. */
    public string $assignmentSectionFilter = '';

    public bool $showNewAssignmentForm = false;

    public function mount(): void
    {
        $this->attendanceDate = now()->toDateString();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'currentPassword' => __('Current Password'),
            'newPassword' => __('New Password'),
            'newPasswordConfirmation' => __('Confirm Password'),
            'newAvatar' => __('Profile Picture'),
            'newMaterials.*' => __('File'),
            'complaintSubject' => __('Complaint Subject'),
            'complaintBody' => __('Body'),
            'newAssignmentSectionId' => __('Section'),
            'newAssignmentTitle' => __('Title'),
            'newAssignmentDescription' => __('Description'),
            'newAssignmentDueDate' => __('Due Date'),
            'newExamSectionId' => __('Section'),
            'newExamName' => __('Exam Name'),
            'newExamDate' => __('Date'),
            'newExamMaxScore' => __('Max Score'),
            'newExamNote' => __('Note'),
        ];
    }

    public function updatePassword(): void
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer || ! Hash::check($this->currentPassword, $trainer->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => __('The provided credentials do not match our records.'),
            ]);
        }

        $this->validate([
            'newPassword' => ['required', 'string', 'min:6', 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string', 'min:6'],
        ]);

        $trainer->update(['password' => $this->newPassword]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->portalToast(__('Password updated successfully'));
    }

    public function updateProfile(): void
    {
        $this->validate([
            'newAvatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $trainer = Auth::guard('trainer')->user();

        if ($trainer && $this->newAvatar) {
            $trainer->addMedia($this->newAvatar->getRealPath())
                ->usingFileName($this->newAvatar->getClientOriginalName())
                ->toMediaCollection('main');
        }

        $this->reset('newAvatar');
        $this->portalToast(__('Profile updated successfully'));
    }

    public function removeAvatar(): void
    {
        $trainer = Auth::guard('trainer')->user();
        $trainer?->clearMediaCollection('main');

        $this->reset('newAvatar');
        $this->portalToast(__('Profile picture removed'));
    }

    public function openAttendance(int $sectionId): void
    {
        $this->activeTab = 'attendance';
        $this->attendanceSectionId = $sectionId;
        $this->loadAttendance();
    }

    /** Jump the attendance editor to a session that was already recorded. */
    public function goToSession(int $sectionId, string $date): void
    {
        $this->activeTab = 'attendance';
        $this->attendanceSectionId = $sectionId;
        $this->attendanceDate = $date;
        $this->loadAttendance();
    }

    public function loadAttendance(): void
    {
        if (! $this->attendanceSectionId) {
            return;
        }
        $existing = Attendance::query()
            ->where('section_id', $this->attendanceSectionId)
            ->whereDate('date', $this->attendanceDate)
            ->get()
            ->keyBy('student_id');

        $section = Section::query()->with('registrations.student')->find($this->attendanceSectionId);
        $this->attendanceStatuses = [];
        $this->attendanceNotes = [];
        $this->attendanceEditReason = '';
        $this->attendanceDayHasRecords = $existing->isNotEmpty();
        foreach ($section?->registrations ?? [] as $reg) {
            $row = $existing->get($reg->student_id);
            $this->attendanceStatuses[$reg->student_id] = $row?->status ?? 'present';
            $this->attendanceNotes[$reg->student_id] = $row?->note ?? '';
        }
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            $this->attendanceStatuses[$studentId] = $status;
        }
    }

    public function markAll(string $status): void
    {
        if (! in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            return;
        }
        foreach (array_keys($this->attendanceStatuses) as $studentId) {
            $this->attendanceStatuses[$studentId] = $status;
        }
    }

    /** @return array<string, int> */
    #[Computed]
    public function attendanceCounts(): array
    {
        $tally = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($this->attendanceStatuses as $status) {
            if (isset($tally[$status])) {
                $tally[$status]++;
            }
        }

        return $tally;
    }

    #[Computed]
    public function attendanceRate(): float
    {
        $total = count($this->attendanceStatuses);
        if ($total === 0) {
            return 0.0;
        }
        $present = $this->attendanceCounts['present'] + $this->attendanceCounts['late'];

        return round(($present / $total) * 100, 1);
    }

    public function saveAttendance(): void
    {
        if (! $this->attendanceSectionId) {
            return;
        }

        AuditReason::using($this->attendanceEditReason ?: null, function (): void {
            Attendance::recordDay(
                $this->attendanceSectionId,
                $this->attendanceDate,
                $this->attendanceStatuses,
                $this->attendanceNotes,
                Auth::guard('trainer')->user(),
            );
        });

        $this->attendanceEditReason = '';

        $this->portalToast(__('Attendance saved'));
    }

    /**
     * Flush attendance that was taken while the device was offline.
     *
     * The browser queues each day's sheet in localStorage and replays it here
     * once the connection is back. Every entry is re-checked against the
     * trainer's own sections — the payload comes from the client and cannot be
     * trusted to only contain sections they teach.
     *
     * @param  array<int, array{section_id: mixed, date: mixed, statuses?: array<mixed, mixed>, notes?: array<mixed, mixed>, reason?: string|null}>  $entries
     * @return array{synced: int, rejected: int}
     */
    public function syncOfflineAttendance(array $entries): array
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer) {
            return ['synced' => 0, 'rejected' => count($entries)];
        }

        $ownSectionIds = Section::query()
            ->where('trainer_id', $trainer->id)
            ->pluck('id')
            ->all();

        $allowedStatuses = ['present', 'absent', 'late', 'excused'];
        $synced = 0;
        $rejected = 0;

        foreach ($entries as $entry) {
            $sectionId = (int) ($entry['section_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');

            if (! in_array($sectionId, $ownSectionIds, true) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $rejected++;

                continue;
            }

            $statuses = [];
            foreach ((array) ($entry['statuses'] ?? []) as $studentId => $status) {
                if (in_array($status, $allowedStatuses, true)) {
                    $statuses[(int) $studentId] = $status;
                }
            }

            if ($statuses === []) {
                $rejected++;

                continue;
            }

            $notes = [];
            foreach ((array) ($entry['notes'] ?? []) as $studentId => $note) {
                $notes[(int) $studentId] = is_string($note) ? mb_substr($note, 0, 255) : null;
            }

            AuditReason::using(
                $entry['reason'] ?? __('Synced from offline attendance'),
                fn () => Attendance::recordDay($sectionId, $date, $statuses, $notes, $trainer),
            );

            $synced++;
        }

        if ($synced > 0) {
            $this->loadAttendance();
            $this->portalToast(__(':count offline session(s) synced', ['count' => $synced]));
        }

        return ['synced' => $synced, 'rejected' => $rejected];
    }

    // -------------------------------------------------------------------------
    // Exams & grades
    // -------------------------------------------------------------------------

    public function createExam(): void
    {
        $trainer = Auth::guard('trainer')->user();

        $this->validate([
            'newExamSectionId' => ['required', 'integer'],
            'newExamName' => ['required', 'string', 'max:255'],
            'newExamDate' => ['required', 'date'],
            'newExamMaxScore' => ['required', 'numeric', 'min:1'],
            'newExamNote' => ['nullable', 'string', 'max:1000'],
        ]);

        // A trainer may only create exams for their own sections.
        $section = $trainer?->sections()->find($this->newExamSectionId);

        if (! $section) {
            throw ValidationException::withMessages([
                'newExamSectionId' => __('Section not found.'),
            ]);
        }

        $exam = Exam::create([
            'section_id' => $section->id,
            'name' => $this->newExamName,
            'date' => $this->newExamDate,
            'max_score' => (float) $this->newExamMaxScore,
            'note' => $this->newExamNote ?: null,
        ]);

        $this->reset(['newExamSectionId', 'newExamName', 'newExamDate', 'newExamMaxScore', 'newExamNote']);
        $this->newExamMaxScore = '100';

        $this->openExamGrades($exam->id);
        $this->portalToast(__('Exam created successfully'));
    }

    /** Load the grade sheet for one of the trainer's own exams. */
    public function openExamGrades(int $examId): void
    {
        $exam = $this->trainerExam($examId);

        if (! $exam) {
            return;
        }

        $this->activeTab = 'exams';
        $this->gradingExamId = $exam->id;

        $existing = $exam->grades()->get()->keyBy('student_id');

        $this->gradeInputs = [];
        $this->gradeNotes = [];

        foreach ($exam->section?->registrations ?? [] as $registration) {
            $row = $existing->get($registration->student_id);
            $this->gradeInputs[$registration->student_id] = $row?->score !== null ? (string) $row->score : '';
            $this->gradeNotes[$registration->student_id] = $row?->note ?? '';
        }
    }

    public function closeExamGrades(): void
    {
        $this->gradingExamId = null;
        $this->gradeInputs = [];
        $this->gradeNotes = [];
    }

    public function saveGrades(): void
    {
        $exam = $this->trainerExam($this->gradingExamId);

        if (! $exam) {
            return;
        }

        $max = (float) $exam->max_score;

        foreach ($this->gradeInputs as $studentId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }

            if (! is_numeric($score) || (float) $score < 0 || (float) $score > $max) {
                throw ValidationException::withMessages([
                    'gradeInputs.'.$studentId => __('Score must be between 0 and :max', ['max' => $max]),
                ]);
            }
        }

        foreach ($this->gradeInputs as $studentId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }

            ExamGrade::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => (int) $studentId],
                ['score' => (float) $score, 'note' => $this->gradeNotes[$studentId] ?? null],
            );
        }

        $this->portalToast(__('Grades saved successfully'));
    }

    /** Publish / unpublish results — students only see published grades. */
    public function togglePublishGrades(int $examId): void
    {
        $exam = $this->trainerExam($examId);

        if (! $exam) {
            return;
        }

        $exam->update([
            'grades_published_at' => $exam->isGradesPublished() ? null : now(),
        ]);

        $this->portalToast($exam->isGradesPublished()
            ? __('Grades published')
            : __('Grades hidden from students'));
    }

    /** An exam that belongs to one of the signed-in trainer's sections, or null. */
    protected function trainerExam(?int $examId): ?Exam
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer || ! $examId) {
            return null;
        }

        return Exam::query()
            ->with(['section.registrations.student', 'grades'])
            ->whereHas('section', fn ($q) => $q->where('trainer_id', $trainer->id))
            ->find($examId);
    }

    public function openMaterials(int $sectionId): void
    {
        $this->activeTab = 'materials';
        $this->materialsSectionId = $sectionId;
    }

    public function uploadMaterials(): void
    {
        $this->validate([
            'newMaterials' => ['required', 'array', 'min:1'],
            'newMaterials.*' => ['file', 'max:20480'],
        ]);

        $section = Section::find($this->materialsSectionId);
        $trainer = Auth::guard('trainer')->user();
        if (! $section || ! $trainer || $section->trainer_id !== $trainer->id) {
            return;
        }

        foreach ($this->newMaterials as $file) {
            $section->addMedia($file->getRealPath())
                ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('materials');
        }

        $this->reset('newMaterials');
        $this->portalToast(__('Materials uploaded'));
    }

    public function submitComplaint(): void
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer) {
            return;
        }

        $this->validate([
            'complaintSubject' => ['required', 'string', 'max:255'],
            'complaintBody' => ['required', 'string', 'min:10'],
        ]);

        $complaint = $trainer->complaints()->create([
            'subject' => $this->complaintSubject,
            'body' => $this->complaintBody,
            'status' => Complaint::STATUS_OPEN,
        ]);

        app(ComplaintAlertService::class)->notifyNewComplaint($complaint);

        $this->reset(['complaintSubject', 'complaintBody']);
        $this->portalToast(__('Complaint submitted successfully'));
    }

    public function removeMaterial(int $mediaId): void
    {
        $trainer = Auth::guard('trainer')->user();
        if (! $trainer) {
            return;
        }
        $media = Media::query()
            ->where('id', $mediaId)
            ->where('model_type', Section::class)
            ->whereIn('model_id', $trainer->sections()->pluck('id'))
            ->first();
        $media?->delete();
    }

    public function createAssignment(): void
    {
        $trainer = Auth::guard('trainer')->user();

        $this->validate([
            'newAssignmentSectionId' => ['required', 'integer'],
            'newAssignmentTitle' => ['required', 'string', 'max:255'],
            'newAssignmentDescription' => ['nullable', 'string'],
            'newAssignmentDueDate' => ['nullable', 'date'],
        ]);

        $section = $trainer?->sections()->find($this->newAssignmentSectionId);
        if (! $section) {
            return;
        }

        $assignment = Assignment::create([
            'section_id' => $section->id,
            'trainer_id' => $trainer->id,
            'title' => $this->newAssignmentTitle,
            'description' => $this->newAssignmentDescription ?: null,
            'due_date' => $this->newAssignmentDueDate ?: null,
        ]);

        $students = $section->registrations()->with('student')->get()->pluck('student')->filter();
        if ($students->isNotEmpty()) {
            Notification::send($students, new AssignmentCreated($assignment));
        }

        $this->reset(['newAssignmentSectionId', 'newAssignmentTitle', 'newAssignmentDescription', 'newAssignmentDueDate']);
        $this->showNewAssignmentForm = false;
        $this->portalToast(__('Assignment created'));
    }

    public function render()
    {
        $trainer = Auth::guard('trainer')->user();

        $sections = $trainer->sections()
            ->with(['subject', 'times.room', 'registrations', 'attendances:id,section_id,date,status'])
            ->orderByDesc('id')
            ->get();

        // Keyed by section id so both the sections list and the attendance tab
        // can read the held/remaining counts without re-querying.
        $scheduleSummaries = $sections->mapWithKeys(
            fn (Section $section) => [$section->id => SectionScheduleService::summary($section)]
        );

        $transactions = collect();
        if ($trainer?->wallet) {
            $transactions = Transaction::query()
                ->where('wallet_id', $trainer->wallet->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        $attendanceSection = null;
        if ($this->attendanceSectionId) {
            $attendanceSection = Section::query()
                ->with('registrations.student')
                ->find($this->attendanceSectionId);
        }

        $materialsSection = null;
        if ($this->materialsSectionId) {
            $materialsSection = Section::query()->find($this->materialsSectionId);
        }

        $complaints = $trainer
            ? $trainer->complaints()->notArchived()->orderByDesc('created_at')->limit(50)->get()
            : collect();

        $assignments = collect();
        if ($trainer) {
            $query = $trainer->assignments()->with('section')->withCount('submissions');

            if ($this->assignmentSectionFilter === '') {
                // Default view hides assignments whose section has already ended;
                // picking that section (or "all") in the filter brings them back.
                $query->whereHas('section', fn ($section) => $section->where(
                    fn ($ended) => $ended->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', now()->toDateString())
                ));
            } elseif ($this->assignmentSectionFilter !== 'all') {
                $query->where('section_id', (int) $this->assignmentSectionFilter);
            }

            $assignments = $query->orderByDesc('due_date')->get();
        }

        $exams = $trainer
            ? $trainer->exams()->with('section')->withCount('grades')->orderByDesc('date')->get()
            : collect();

        $gradingExam = $this->gradingExamId ? $this->trainerExam($this->gradingExamId) : null;

        return view('livewire.trainer-dashboard', [
            'exams' => $exams,
            'gradingExam' => $gradingExam,
            'trainer' => $trainer,
            'notifications' => $trainer->notifications()->limit(15)->get(),
            'unreadNotificationsCount' => $trainer->unreadNotifications()->count(),
            'sections' => $sections,
            'scheduleSummaries' => $scheduleSummaries,
            'transactions' => $transactions,
            'attendanceSection' => $attendanceSection,
            'materialsSection' => $materialsSection,
            'complaints' => $complaints,
            'assignments' => $assignments,
        ]);
    }
}
