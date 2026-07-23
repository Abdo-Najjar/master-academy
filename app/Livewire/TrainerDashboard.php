<?php

namespace App\Livewire;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Section;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class TrainerDashboard extends Component
{
    use WithFileUploads;

    public string $activeTab = 'sections';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public TemporaryUploadedFile|UploadedFile|null $newAvatar = null;

    /** @var int|null */
    public ?int $attendanceSectionId = null;

    public string $attendanceDate = '';

    /** @var array<int, string> student_id => status */
    public array $attendanceStatuses = [];

    /** @var array<int, string> student_id => optional note */
    public array $attendanceNotes = [];

    /** @var int|null */
    public ?int $materialsSectionId = null;

    /** @var array<int, TemporaryUploadedFile|UploadedFile> */
    public array $newMaterials = [];

    public string $complaintSubject = '';

    public string $complaintBody = '';

    public ?int $newAssignmentSectionId = null;

    public string $newAssignmentTitle = '';

    public string $newAssignmentDescription = '';

    public string $newAssignmentDueDate = '';

    public ?float $newAssignmentMaxPoints = null;

    public function mount(): void
    {
        $this->attendanceDate = now()->toDateString();
    }

    public function logout(): void
    {
        Auth::guard('trainer')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('trainer.login'), navigate: true);
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
            'complaintSubject' => __('Subject'),
            'complaintBody' => __('Body'),
            'newAssignmentSectionId' => __('Section'),
            'newAssignmentTitle' => __('Title'),
            'newAssignmentDescription' => __('Description'),
            'newAssignmentDueDate' => __('Due Date'),
            'newAssignmentMaxPoints' => __('Max Points'),
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
        session()->flash('message', __('Password updated successfully'));
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
        session()->flash('message', __('Profile updated successfully'));
    }

    public function removeAvatar(): void
    {
        $trainer = Auth::guard('trainer')->user();
        $trainer?->clearMediaCollection('main');

        $this->reset('newAvatar');
        session()->flash('message', __('Profile picture removed'));
    }

    public function markNotificationRead(string $notificationId): void
    {
        Auth::guard('trainer')->user()?->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllNotificationsRead(): void
    {
        Auth::guard('trainer')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function openAttendance(int $sectionId): void
    {
        $this->activeTab = 'attendance';
        $this->attendanceSectionId = $sectionId;
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
        foreach ($this->attendanceStatuses as $studentId => $status) {
            Attendance::query()->updateOrCreate(
                ['section_id' => $this->attendanceSectionId, 'student_id' => $studentId, 'date' => $this->attendanceDate],
                ['status' => $status, 'note' => $this->attendanceNotes[$studentId] ?? null]
            );
        }
        session()->flash('message', __('Attendance saved'));
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
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('materials');
        }

        $this->reset('newMaterials');
        session()->flash('message', __('Materials uploaded'));
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

        $trainer->complaints()->create([
            'subject' => $this->complaintSubject,
            'body' => $this->complaintBody,
            'status' => Complaint::STATUS_OPEN,
        ]);

        $this->reset(['complaintSubject', 'complaintBody']);
        session()->flash('message', __('Complaint submitted successfully'));
    }

    public function removeMaterial(int $mediaId): void
    {
        $trainer = Auth::guard('trainer')->user();
        if (! $trainer) {
            return;
        }
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
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
            'newAssignmentMaxPoints' => ['nullable', 'numeric', 'min:0'],
        ]);

        $section = $trainer?->sections()->find($this->newAssignmentSectionId);
        if (! $section) {
            return;
        }

        Assignment::create([
            'section_id' => $section->id,
            'trainer_id' => $trainer->id,
            'title' => $this->newAssignmentTitle,
            'description' => $this->newAssignmentDescription ?: null,
            'due_date' => $this->newAssignmentDueDate ?: null,
            'max_points' => $this->newAssignmentMaxPoints,
        ]);

        $this->reset(['newAssignmentSectionId', 'newAssignmentTitle', 'newAssignmentDescription', 'newAssignmentDueDate', 'newAssignmentMaxPoints']);
        session()->flash('message', __('Assignment created'));
    }

    public function render()
    {
        $trainer = Auth::guard('trainer')->user();

        $sections = $trainer->sections()
            ->with(['subject', 'times.room', 'registrations'])
            ->orderByDesc('id')
            ->get();

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

        $assignments = $trainer
            ? $trainer->assignments()->with('section')->withCount('submissions')->orderByDesc('due_date')->get()
            : collect();

        return view('livewire.trainer-dashboard', [
            'trainer' => $trainer,
            'notifications' => $trainer->notifications()->limit(15)->get(),
            'unreadNotificationsCount' => $trainer->unreadNotifications()->count(),
            'sections' => $sections,
            'transactions' => $transactions,
            'attendanceSection' => $attendanceSection,
            'materialsSection' => $materialsSection,
            'complaints' => $complaints,
            'assignments' => $assignments,
        ]);
    }
}
