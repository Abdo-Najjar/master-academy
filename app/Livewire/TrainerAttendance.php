<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Section;
use App\Services\AttendanceAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TrainerAttendance extends Component
{
    public ?int $sectionId = null;

    public string $date = '';

    /** @var array<int,string> student_id => status */
    public array $statuses = [];

    /** @var array<int,string> student_id => optional note */
    public array $notes = [];

    public bool $saving = false;

    public function mount(): void
    {
        $this->date = now()->toDateString();

        $trainer = Auth::guard('trainer')->user();
        $first = $trainer?->sections()
            ->orderByDesc('id')
            ->first();
        $this->sectionId = $first?->id;

        if ($this->sectionId) {
            $this->loadAttendance();
        }
    }

    public function updatedSectionId(): void
    {
        $this->loadAttendance();
    }

    public function updatedDate(): void
    {
        $this->loadAttendance();
    }

    public function loadAttendance(): void
    {
        if (! $this->sectionId) {
            $this->statuses = [];
            $this->notes = [];
            return;
        }

        $section = $this->currentSection();
        if (! $section) {
            return;
        }

        $existing = Attendance::query()
            ->where('section_id', $this->sectionId)
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('student_id');

        $this->statuses = [];
        $this->notes = [];

        foreach ($section->registrations as $reg) {
            $row = $existing->get($reg->student_id);
            $this->statuses[$reg->student_id] = $row?->status ?? 'present';
            $this->notes[$reg->student_id] = $row?->note ?? '';
        }
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function markAll(string $status): void
    {
        if (! in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            return;
        }
        foreach (array_keys($this->statuses) as $studentId) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function save(): void
    {
        if (! $this->sectionId) {
            return;
        }

        $trainer = Auth::guard('trainer')->user();
        $section = Section::find($this->sectionId);
        if (! $trainer || ! $section || $section->trainer_id !== $trainer->id) {
            return;
        }

        $this->saving = true;

        foreach ($this->statuses as $studentId => $status) {
            Attendance::query()->updateOrCreate(
                [
                    'section_id' => $this->sectionId,
                    'student_id' => $studentId,
                    'date' => $this->date,
                ],
                [
                    'status' => $status,
                    'note' => $this->notes[$studentId] ?? null,
                ]
            );
        }

        app(AttendanceAlertService::class)->checkForSection($section, $this->statuses);

        $this->saving = false;
        session()->flash('message', __('Attendance saved successfully'));
    }

    public function logout(): void
    {
        Auth::guard('trainer')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('trainer.login'), navigate: true);
    }

    public function currentSection(): ?Section
    {
        if (! $this->sectionId) {
            return null;
        }

        return Section::query()
            ->with(['registrations.student', 'subject'])
            ->find($this->sectionId);
    }

    #[Computed]
    public function counts(): array
    {
        $tally = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($this->statuses as $status) {
            if (isset($tally[$status])) {
                $tally[$status]++;
            }
        }
        return $tally;
    }

    #[Computed]
    public function attendanceRate(): float
    {
        $total = count($this->statuses);
        if ($total === 0) {
            return 0.0;
        }
        $present = $this->counts['present'] + $this->counts['late'];
        return round(($present / $total) * 100, 1);
    }

    public function render()
    {
        $trainer = Auth::guard('trainer')->user();

        $sections = $trainer?->sections()
            ->with('subject')
            ->orderByDesc('id')
            ->get() ?? collect();

        $section = $this->currentSection();

        $recentSummaries = collect();
        if ($section) {
            $recentSummaries = Attendance::query()
                ->selectRaw('date, status, COUNT(*) as total')
                ->where('section_id', $section->id)
                ->whereBetween('date', [
                    Carbon::parse($this->date)->subDays(13)->toDateString(),
                    $this->date,
                ])
                ->groupBy('date', 'status')
                ->orderBy('date')
                ->get()
                ->groupBy('date');
        }

        return view('livewire.trainer-attendance', [
            'trainer' => $trainer,
            'sections' => $sections,
            'section' => $section,
            'recentSummaries' => $recentSummaries,
        ]);
    }
}
