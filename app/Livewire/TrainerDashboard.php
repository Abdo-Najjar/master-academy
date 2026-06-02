<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Section;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    /** @var int|null */
    public ?int $materialsSectionId = null;

    /** @var array<int, TemporaryUploadedFile|UploadedFile> */
    public array $newMaterials = [];

    public string $complaintSubject = '';

    public string $complaintBody = '';

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

    public function openAttendance(int $sectionId): void
    {
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
            ->pluck('status', 'student_id');

        $section = Section::query()->with('registrations.student')->find($this->attendanceSectionId);
        $this->attendanceStatuses = [];
        foreach ($section?->registrations ?? [] as $reg) {
            $this->attendanceStatuses[$reg->student_id] = $existing[$reg->student_id] ?? 'present';
        }
    }

    public function saveAttendance(): void
    {
        if (! $this->attendanceSectionId) {
            return;
        }
        foreach ($this->attendanceStatuses as $studentId => $status) {
            Attendance::query()->updateOrCreate(
                ['section_id' => $this->attendanceSectionId, 'student_id' => $studentId, 'date' => $this->attendanceDate],
                ['status' => $status]
            );
        }
        session()->flash('message', __('Attendance saved'));
    }

    public function openMaterials(int $sectionId): void
    {
        $this->materialsSectionId = $sectionId;
    }

    public function uploadMaterials(): void
    {
        $this->validate(['newMaterials.*' => ['file', 'max:20480']]);

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
            ? $trainer->complaints()->orderByDesc('created_at')->limit(50)->get()
            : collect();

        $loginActivities = $trainer
            ? $trainer->loginActivities()->orderByDesc('logged_in_at')->limit(10)->get()
            : collect();

        return view('livewire.trainer-dashboard', [
            'trainer' => $trainer,
            'sections' => $sections,
            'transactions' => $transactions,
            'attendanceSection' => $attendanceSection,
            'materialsSection' => $materialsSection,
            'complaints' => $complaints,
            'loginActivities' => $loginActivities,
        ]);
    }
}
