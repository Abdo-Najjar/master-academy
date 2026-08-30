<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Section;
use App\Services\AttendanceAlertService;
use App\Support\AuditReason;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

class TakeAttendance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected string $view = 'filament.admin.pages.take-attendance';

    /** Sits with the other attendance pages at the top, just under the sheet. */
    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public ?int $sectionId = null;

    public string $date = '';

    /** First day of the month shown in the day picker, as `Y-m-d`. */
    public string $calendarMonth = '';

    /** @var array<int,string> student_id => status */
    public array $statuses = [];

    /** @var array<int,string> student_id => optional note */
    public array $notes = [];

    /** Why an already-recorded day is being changed; stored in the audit log. */
    public string $editReason = '';

    /** True once the selected day already has attendance rows. */
    public bool $isEditingExistingDay = false;

    public static function getNavigationLabel(): string
    {
        return __('Take Attendance');
    }

    public function getTitle(): string
    {
        return __('Take Attendance');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('attendance.update') ?? false;
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->calendarMonth = now()->startOfMonth()->toDateString();
        $this->form->fill([
            'sectionId' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('sectionId')
                            ->label(__('Section'))
                            ->options(fn () => Section::query()
                                ->with('subject')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => $s->name
                                        .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : ''),
                                ]))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->sectionId = $state ? (int) $state : null;
                                $this->snapToSessionDay();
                                $this->loadAttendance();
                            }),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Move the selection onto a day the section actually meets. Picking a
     * section whose timetable excludes the currently selected date would
     * otherwise leave the sheet pointing at a day it refuses to save.
     */
    protected function snapToSessionDay(): void
    {
        $section = $this->currentSection();

        if (! $section || $section->meetsOn($this->date)) {
            $this->calendarMonth = Carbon::parse($this->date)->startOfMonth()->toDateString();

            return;
        }

        // The most recent lesson on or before today is the one a user almost
        // always wants; fall back to the next upcoming one for a course that
        // has not started yet.
        $cursor = Carbon::parse($this->date)->startOfDay();

        for ($i = 0; $i < 366; $i++) {
            if ($section->meetsOn($cursor)) {
                $this->date = $cursor->toDateString();
                $this->calendarMonth = $cursor->copy()->startOfMonth()->toDateString();

                return;
            }
            $cursor->subDay();
        }

        $cursor = Carbon::parse($this->date)->startOfDay();

        for ($i = 0; $i < 366; $i++) {
            if ($section->meetsOn($cursor)) {
                $this->date = $cursor->toDateString();
                $this->calendarMonth = $cursor->copy()->startOfMonth()->toDateString();

                return;
            }
            $cursor->addDay();
        }
    }

    /**
     * A day the sheet may be opened on: either the section meets then, or it
     * already has attendance on file.
     *
     * The second half matters for history. A day recorded before the timetable
     * existed — or before it was changed — is off-timetable now, and locking it
     * would leave a wrong record on the books with no way to correct it. New
     * attendance still cannot be created on a day the section does not meet.
     */
    protected function canOpen(string $date): bool
    {
        $section = $this->currentSection();

        if (! $section || $section->meetsOn($date)) {
            return true;
        }

        return Attendance::query()
            ->where('section_id', $this->sectionId)
            ->whereDate('date', $date)
            ->exists();
    }

    public function selectDate(string $date): void
    {
        if (! $this->canOpen($date)) {
            Notification::make()
                ->warning()
                ->title(__('This section does not meet on that day.'))
                ->body(__('Lesson days: :days', ['days' => $this->scheduleSummary()]))
                ->send();

            return;
        }

        $this->date = Carbon::parse($date)->toDateString();
        $this->calendarMonth = Carbon::parse($date)->startOfMonth()->toDateString();
        $this->loadAttendance();
    }

    public function shiftMonth(int $months): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth ?: now())
            ->startOfMonth()
            ->addMonths($months)
            ->toDateString();

        unset($this->calendar);
    }

    /** Weekday names the selected section meets on, for the hint line. */
    public function scheduleSummary(): string
    {
        $section = $this->currentSection();

        if (! $section) {
            return '';
        }

        $labels = [
            'saturday' => __('Saturday'),
            'sunday' => __('Sunday'),
            'monday' => __('Monday'),
            'tuesday' => __('Tuesday'),
            'wednesday' => __('Wednesday'),
            'thursday' => __('Thursday'),
            'friday' => __('Friday'),
        ];

        $days = array_map(
            fn (string $day): string => $labels[$day] ?? $day,
            $section->scheduledWeekdays(),
        );

        return $days === [] ? __('Every day') : implode('، ', $days);
    }

    /**
     * The visible month as a Saturday-first grid. Each day carries everything
     * the view needs to colour it: whether the section meets then, whether
     * attendance is already on file, and how that day was tallied.
     *
     * @return array{month: string, days: list<array{date: string, day: int, inMonth: bool, isToday: bool, isSelected: bool, isSessionDay: bool, isRecorded: bool, present: int, absent: int}>}
     */
    #[Computed]
    public function calendar(): array
    {
        $month = Carbon::parse($this->calendarMonth ?: now())->startOfMonth();

        $gridStart = $month->copy();
        while ($gridStart->dayOfWeek !== Carbon::SATURDAY) {
            $gridStart->subDay();
        }

        $gridEnd = $month->copy()->endOfMonth();
        while ($gridEnd->dayOfWeek !== Carbon::FRIDAY) {
            $gridEnd->addDay();
        }

        $section = $this->currentSection();
        $recorded = $this->recordedDaysBetween($gridStart, $gridEnd);
        $today = now()->toDateString();

        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $key = $cursor->toDateString();
            $tally = $recorded[$key] ?? null;

            $days[] = [
                'date' => $key,
                'day' => $cursor->day,
                'inMonth' => $cursor->month === $month->month,
                'isToday' => $key === $today,
                'isSelected' => $key === $this->date,
                'isSessionDay' => $section ? $section->meetsOn($cursor) : true,
                'isRecorded' => $tally !== null,
                'present' => $tally['present'] ?? 0,
                'absent' => $tally['absent'] ?? 0,
            ];

            $cursor->addDay();
        }

        return [
            'month' => $month->translatedFormat('F Y'),
            'days' => $days,
        ];
    }

    /**
     * Present/absent tallies for every day in the range that already has
     * attendance on file, keyed by `Y-m-d`.
     *
     * @return array<string, array{present: int, absent: int}>
     */
    protected function recordedDaysBetween(Carbon $from, Carbon $to): array
    {
        if (! $this->sectionId) {
            return [];
        }

        return Attendance::query()
            ->where('section_id', $this->sectionId)
            ->whereBetween('date', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->get(['date', 'status'])
            ->groupBy(fn (Attendance $row): string => $row->date->toDateString())
            ->map(fn ($rows): array => [
                // "Late" still means the student turned up, so it counts as
                // present here exactly as it does on the records sheet.
                'present' => $rows->whereIn('status', ['present', 'late'])->count(),
                'absent' => $rows->where('status', 'absent')->count(),
            ])
            ->all();
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
        $this->editReason = '';
        $this->isEditingExistingDay = $existing->isNotEmpty();

        foreach ($section->rosterOn($this->date) as $reg) {
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
            Notification::make()->warning()->title(__('Please select a section first.'))->send();

            return;
        }

        $section = Section::with('times')->find($this->sectionId);
        if (! $section) {
            return;
        }

        // The picker already hides off-timetable days, but the date also
        // arrives from Livewire state — so the timetable is enforced here too,
        // where it actually guards the write. A day that already has records is
        // allowed through: that is a correction, not a new lesson.
        if (! $this->canOpen($this->date)) {
            Notification::make()
                ->danger()
                ->title(__('This section does not meet on that day.'))
                ->body(__('Lesson days: :days', ['days' => $this->scheduleSummary()]))
                ->send();

            return;
        }

        AuditReason::using($this->editReason, function (): void {
            Attendance::recordDay(
                $this->sectionId,
                $this->date,
                $this->statuses,
                $this->notes,
                auth()->user(),
            );
        });

        app(AttendanceAlertService::class)->checkForSection($section, $this->statuses);

        $this->editReason = '';
        $this->isEditingExistingDay = true;

        // The day just gained records, so the picker must repaint it as taken.
        // Recording also created the day's lesson row, and the cached section
        // still holds the lesson dates as they were before that.
        $this->sectionCache = null;
        unset($this->calendar);

        Notification::make()
            ->success()
            ->title(__('Attendance saved successfully'))
            ->send();
    }

    /** @var array{key: string, section: ?Section}|null */
    protected ?array $sectionCache = null;

    public function currentSection(): ?Section
    {
        if (! $this->sectionId) {
            return null;
        }

        // The calendar, the roster and the save guard all ask for the section
        // during a single render; without this the page would re-query it — and
        // rebuild the roster — a dozen times per request.
        $key = $this->sectionId.'|'.$this->date;

        if ($this->sectionCache && $this->sectionCache['key'] === $key) {
            return $this->sectionCache['section'];
        }

        $section = Section::query()
            ->with(['subject', 'trainer', 'times'])
            ->find($this->sectionId);

        // The sheet always shows the roster as it stood on the day being
        // recorded, so backfilling old lessons never lists students who joined
        // later. Swapping the relation keeps the view working off
        // `$section->registrations` unchanged.
        $section?->setRelation('registrations', $section->rosterOn($this->date));

        $this->sectionCache = ['key' => $key, 'section' => $section];

        return $section;
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
}
