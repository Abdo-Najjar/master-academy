<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithTrainerAuth;
use App\Livewire\Concerns\NotifiesPortal;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Complaint;
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

        return view('livewire.trainer-dashboard', [
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
