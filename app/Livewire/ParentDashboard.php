<?php

namespace App\Livewire;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ParentDashboard extends Component
{
    public string $activeTab = 'overview';

    public ?int $selectedStudentId = null;

    public function mount(): void
    {
        $parent = Auth::guard('parent')->user();
        if ($parent) {
            $first = $parent->students()->first();
            if ($first) {
                $this->selectedStudentId = $first->id;
            }
        }
    }

    public function logout(): void
    {
        Auth::guard('parent')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('parent.login'), navigate: true);
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectStudent(int $studentId): void
    {
        $parent = Auth::guard('parent')->user();
        if (! $parent) {
            return;
        }

        if ($parent->students()->where('id', $studentId)->exists()) {
            $this->selectedStudentId = $studentId;
        }
    }

    #[Computed]
    public function selectedStudent(): ?Student
    {
        if (! $this->selectedStudentId) {
            return null;
        }

        $parent = Auth::guard('parent')->user();
        if (! $parent) {
            return null;
        }

        return $parent->students()
            ->with(['registrations.section.subject', 'registrations.section.times', 'attendances', 'examGrades.exam'])
            ->find($this->selectedStudentId);
    }

    public function render()
    {
        $parent = Auth::guard('parent')->user();
        $students = $parent ? $parent->students()->with(['registrations.section.subject'])->get() : collect();

        $student = $this->selectedStudent;

        $registrations = $student
            ? $student->registrations()->with(['section.subject', 'section.trainer', 'section.times', 'paymentType'])->get()
            : collect();

        $scheduleGrid = [
            'sunday' => [], 'monday' => [], 'tuesday' => [],
            'wednesday' => [], 'thursday' => [], 'friday' => [], 'saturday' => [],
        ];

        foreach ($registrations as $reg) {
            $section = $reg->section;
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

        $attendances = $student
            ? $student->attendances()->with('section.subject')->orderByDesc('date')->limit(50)->get()
            : collect();

        $examGrades = $student
            ? $student->examGrades()->with(['exam.section.subject'])->orderByDesc('created_at')->limit(50)->get()
            : collect();

        $certificates = $student
            ? $student->certificates()->with(['template', 'section.subject'])->orderByDesc('issued_at')->get()
            : collect();

        return view('livewire.parent-dashboard', [
            'parent' => $parent,
            'students' => $students,
            'student' => $student,
            'registrations' => $registrations,
            'schedule' => $scheduleGrid,
            'attendances' => $attendances,
            'examGrades' => $examGrades,
            'certificates' => $certificates,
        ]);
    }
}
