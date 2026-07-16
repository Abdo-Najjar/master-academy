<?php

namespace App\Livewire;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Complaint;
use Bavix\Wallet\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class StudentDashboard extends Component
{
    use WithFileUploads;

    public string $activeTab = 'registrations';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public TemporaryUploadedFile|UploadedFile|null $newAvatar = null;

    public string $complaintSubject = '';

    public string $complaintBody = '';

    public function logout(): void
    {
        Auth::guard('student')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('student.login'), navigate: true);
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
            'complaintSubject' => __('Subject'),
            'complaintBody' => __('Body'),
        ];
    }

    public function updatePassword(): void
    {
        $student = Auth::guard('student')->user();

        if (! $student || ! Hash::check($this->currentPassword, $student->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => __('The provided credentials do not match our records.'),
            ]);
        }

        $this->validate([
            'newPassword' => ['required', 'string', 'min:6', 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string', 'min:6'],
        ]);

        $student->update(['password' => $this->newPassword]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        session()->flash('message', __('Password updated successfully'));
    }

    public function dismissAnnouncement(int $announcementId): void
    {
        $student = Auth::guard('student')->user();
        if (! $student) {
            return;
        }

        $student->dismissedAnnouncements()->syncWithoutDetaching([$announcementId]);
    }

    public function submitComplaint(): void
    {
        $student = Auth::guard('student')->user();

        if (! $student) {
            return;
        }

        $this->validate([
            'complaintSubject' => ['required', 'string', 'max:255'],
            'complaintBody' => ['required', 'string', 'min:10'],
        ]);

        $student->complaints()->create([
            'subject' => $this->complaintSubject,
            'body' => $this->complaintBody,
            'status' => Complaint::STATUS_OPEN,
        ]);

        $this->reset(['complaintSubject', 'complaintBody']);
        session()->flash('message', __('Complaint submitted successfully'));
    }

    public function updateProfile(): void
    {
        $this->validate([
            'newAvatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $student = Auth::guard('student')->user();

        if ($student && $this->newAvatar) {
            $student->addMedia($this->newAvatar->getRealPath())
                ->usingFileName($this->newAvatar->getClientOriginalName())
                ->toMediaCollection('main');
        }

        $this->reset('newAvatar');
        session()->flash('message', __('Profile updated successfully'));
    }

    public function render()
    {
        $student = Auth::guard('student')->user();

        $registrations = $student->registrations()
            ->with(['section.subject', 'section.trainer', 'section.times.room', 'section.media'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Learning materials the trainers uploaded to the student's sections.
        $materials = collect();
        foreach ($registrations as $reg) {
            $section = $reg->section;
            if (! $section) {
                continue;
            }
            foreach ($section->getMedia('materials') as $media) {
                $materials->push([
                    'section' => $section,
                    'media' => $media,
                ]);
            }
        }

        $dayOrder = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $scheduleGrid = array_fill_keys($dayOrder, []);

        foreach ($registrations as $registration) {
            $section = $registration->section;
            if (! $section || $section->status === 'completed') {
                continue;
            }
            foreach ($section->times as $time) {
                $day = strtolower($time->day);
                if (isset($scheduleGrid[$day])) {
                    $scheduleGrid[$day][] = [
                        'section' => $section,
                        'time' => $time,
                        'start_time' => Carbon::parse($time->start_time)->format('H:i'),
                        'end_time' => Carbon::parse($time->end_time)->format('H:i'),
                    ];
                }
            }
        }
        foreach ($scheduleGrid as $day => $items) {
            usort($scheduleGrid[$day], fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));
        }

        $transactions = collect();
        if ($student?->wallet) {
            $transactions = Transaction::query()
                ->where('wallet_id', $student->wallet->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        $complaints = $student
            ? $student->complaints()->notArchived()->orderByDesc('created_at')->limit(50)->get()
            : collect();

        $loginActivities = $student
            ? $student->loginActivities()->orderByDesc('logged_in_at')->limit(10)->get()
            : collect();

        $announcements = collect();
        if ($student) {
            $dismissedIds = $student->dismissedAnnouncements()->pluck('announcements.id')->all();
            $announcements = Announcement::query()
                ->active()
                ->forStudent($student)
                ->whereNotIn('id', $dismissedIds)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        $certificates = $student
            ? $student->certificates()->with(['template', 'section.subject'])->orderByDesc('issued_at')->get()
            : collect();

        $grades = $student
            ? $student->examGrades()
                ->whereHas('exam', fn ($q) => $q->whereNotNull('grades_published_at'))
                ->with(['exam.section.subject'])
                ->get()
                ->sortByDesc(fn ($g) => $g->exam?->date)
                ->values()
            : collect();

        $sectionIds = $registrations->pluck('section_id');
        $mySubmissions = $student
            ? $student->assignmentSubmissions()->get()->keyBy('assignment_id')
            : collect();

        $assignments = Assignment::query()
            ->whereIn('section_id', $sectionIds)
            ->with('section.subject')
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (Assignment $a) => [
                'assignment' => $a,
                'submission' => $mySubmissions->get($a->id),
            ]);

        return view('livewire.student-dashboard', [
            'student' => $student,
            'registrations' => $registrations,
            'materials' => $materials,
            'schedule' => $scheduleGrid,
            'transactions' => $transactions,
            'complaints' => $complaints,
            'loginActivities' => $loginActivities,
            'announcements' => $announcements,
            'certificates' => $certificates,
            'grades' => $grades,
            'assignments' => $assignments,
        ]);
    }
}
