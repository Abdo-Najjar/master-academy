<?php

namespace App\Filament\Admin\Pages;

use App\Models\Branch;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\SectionTime;
use App\Models\Subject;
use App\Services\SectionScheduleService;
use App\Support\PdfFonts;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SectionsCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.admin.pages.sections-calendar';

    protected static ?int $navigationSort = -1;

    /** A whole month, a fortnight, or a single week. */
    public const VIEW_MONTH = 'month';

    public const VIEW_FORTNIGHT = 'fortnight';

    public const VIEW_WEEK = 'week';

    public ?array $filters = [];

    /** Any date inside the displayed period; the grid is derived from it. */
    public string $cursor;

    public string $span = self::VIEW_MONTH;

    public static function getNavigationLabel(): string
    {
        return __('Sections Calendar');
    }

    public function getTitle(): string
    {
        return __('Sections Calendar');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('section.index') ?? false;
    }

    public function mount(): void
    {
        $this->cursor = now()->startOfMonth()->toDateString();

        $this->form->fill([
            'branch_id' => null,
            'subject_id' => null,
            'section_id' => null,
            'room_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('section_id', null)),
                        Select::make('subject_id')
                            ->label(__('Course'))
                            ->options(fn () => Subject::query()->get()
                                ->mapWithKeys(fn (Subject $s) => [$s->id => $s->getTranslation('name', app()->getLocale(), false)]))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, $set) => $set('section_id', null)),
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(function (Get $get): array {
                                return Section::query()
                                    ->when($get('subject_id'), fn ($q, $subjectId) => $q->where('subject_id', $subjectId))
                                    ->when($get('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId))
                                    ->get()
                                    ->mapWithKeys(fn (Section $s) => [$s->id => $s->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live(),
                        // "Which room is free on Tuesday" is asked as often as
                        // "where does this section meet", and the calendar had
                        // no way to answer it.
                        Select::make('room_id')
                            ->label(__('Room'))
                            ->options(fn (): array => self::roomOptions())
                            ->searchable()
                            ->preload()
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ])
            ->statePath('filters');
    }

    /**
     * Rooms in the order someone walks past them: 1, 2, … 9, 10 — not the
     * lexical 1, 10, 2 a plain `orderBy('number')` gives, because `number` is
     * a free-text label ("A3", "قاعة 2") rather than an integer.
     *
     * @return Collection<int, Room>
     */
    public static function orderedRooms(): Collection
    {
        return Room::query()
            ->get()
            ->sortBy(fn (Room $room): string => self::naturalKey((string) $room->number))
            ->values();
    }

    /** @return array<int, string> room id => "Room 1" */
    public static function roomOptions(): array
    {
        return self::orderedRooms()
            ->mapWithKeys(fn (Room $room): array => [$room->id => __('Room').' '.$room->number])
            ->all();
    }

    /** Zero-pads the digits inside a label so "10" sorts after "9", not after "1". */
    protected static function naturalKey(string $value): string
    {
        return (string) preg_replace_callback(
            '/\d+/',
            fn (array $match): string => str_pad($match[0], 8, '0', STR_PAD_LEFT),
            $value,
        );
    }

    /**
     * Reading order for a lesson: earliest start first, and inside one time
     * slot room 1 before room 2 before room 10. Lessons with no room set come
     * last — they are the exception, not the top of the list.
     */
    public static function slotKey(SectionTime $time): string
    {
        $number = (string) ($time->room?->number ?? '');

        return Carbon::parse($time->start_time)->format('H:i:s')
            .'|'.($number === '' ? '~' : '0'.self::naturalKey($number));
    }

    /** @return array<string, string> */
    public static function viewOptions(): array
    {
        return [
            self::VIEW_MONTH => __('Month'),
            self::VIEW_FORTNIGHT => __('Two Weeks'),
            self::VIEW_WEEK => __('Week'),
        ];
    }

    /**
     * Switch the span on screen, keeping the cursor on a date that is still
     * inside the new period so the view does not jump somewhere unrelated.
     */
    public function setSpan(string $span): void
    {
        if (! array_key_exists($span, self::viewOptions())) {
            return;
        }

        $this->span = $span;
        $this->cursor = $this->periodStart()->toDateString();
    }

    public function previousPeriod(): void
    {
        $this->cursor = match ($this->span) {
            self::VIEW_WEEK => $this->periodStart()->subWeek()->toDateString(),
            self::VIEW_FORTNIGHT => $this->periodStart()->subWeeks(2)->toDateString(),
            default => Carbon::parse($this->cursor)->subMonthNoOverflow()->startOfMonth()->toDateString(),
        };
    }

    public function nextPeriod(): void
    {
        $this->cursor = match ($this->span) {
            self::VIEW_WEEK => $this->periodStart()->addWeek()->toDateString(),
            self::VIEW_FORTNIGHT => $this->periodStart()->addWeeks(2)->toDateString(),
            default => Carbon::parse($this->cursor)->addMonthNoOverflow()->startOfMonth()->toDateString(),
        };
    }

    public function goToday(): void
    {
        $this->cursor = $this->span === self::VIEW_MONTH
            ? now()->startOfMonth()->toDateString()
            : self::weekStart(now())->toDateString();
    }

    /** The Saturday on or before a date — every grid row starts there. */
    protected static function weekStart(Carbon $date): Carbon
    {
        $start = $date->copy()->startOfDay();

        while ($start->dayOfWeek !== Carbon::SATURDAY) {
            $start->subDay();
        }

        return $start;
    }

    /** First day of the grid currently on screen. */
    public function periodStart(): Carbon
    {
        $cursor = Carbon::parse($this->cursor);

        if ($this->span === self::VIEW_MONTH) {
            return self::weekStart($cursor->copy()->startOfMonth());
        }

        return self::weekStart($cursor);
    }

    /** Last day of the grid currently on screen. */
    public function periodEnd(): Carbon
    {
        $start = $this->periodStart();

        if ($this->span === self::VIEW_WEEK) {
            return $start->copy()->addDays(6);
        }

        if ($this->span === self::VIEW_FORTNIGHT) {
            return $start->copy()->addDays(13);
        }

        $end = Carbon::parse($this->cursor)->endOfMonth();

        while ($end->dayOfWeek !== Carbon::FRIDAY) {
            $end->addDay();
        }

        return $end;
    }

    public function getPeriodLabelProperty(): string
    {
        if ($this->span === self::VIEW_MONTH) {
            return Carbon::parse($this->cursor)->translatedFormat('F Y');
        }

        $start = $this->periodStart();
        $end = $this->periodEnd();

        // "1 – 14 أغسطس 2026", or both months named when the span crosses one.
        $from = $start->month === $end->month
            ? $start->translatedFormat('j')
            : $start->translatedFormat('j F');

        return $from.' – '.$end->translatedFormat('j F Y');
    }

    /**
     * Section times for the active filters, grouped by weekday name
     * (e.g. 'monday' => Collection<SectionTime>). Fetched once per render;
     * each calendar cell reuses this map and only checks the section's
     * active date range in memory instead of re-querying per day.
     *
     * @return array<string, Collection<int, SectionTime>>
     */
    public function getTimesByWeekdayProperty(): array
    {
        $branchId = $this->filters['branch_id'] ?? null;
        $subjectId = $this->filters['subject_id'] ?? null;
        $sectionId = $this->filters['section_id'] ?? null;
        $roomId = $this->filters['room_id'] ?? null;

        return SectionTime::query()
            ->with(['room', 'section.subject', 'section.branch', 'section.trainer'])
            ->whereHas('section', fn ($q) => $q
                ->when($subjectId, fn ($q2) => $q2->where('subject_id', $subjectId))
                ->when($branchId, fn ($q2) => $q2->where('branch_id', $branchId)))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->get()
            ->groupBy('day')
            ->all();
    }

    /**
     * The grid of dates on screen, Saturday-first. A month view is padded with
     * leading/trailing days from the adjacent months so every row is a complete
     * week; the week and fortnight views are already whole weeks.
     *
     * @return list<array{date: Carbon, inMonth: bool}>
     */
    public function getCalendarDaysProperty(): array
    {
        $gridStart = $this->periodStart();
        $gridEnd = $this->periodEnd();
        $month = Carbon::parse($this->cursor)->month;

        $days = [];
        $day = $gridStart->copy();

        while ($day->lte($gridEnd)) {
            $days[] = [
                'date' => $day->copy(),
                // Only the month view dims days belonging to another month.
                'inMonth' => $this->span !== self::VIEW_MONTH || $day->month === $month,
            ];
            $day->addDay();
        }

        return $days;
    }

    /**
     * Lessons occurring on the given date: the weekly timetable rows whose
     * section is active (start_date/end_date range) then, plus the extra and
     * make-up lessons entered for that exact date.
     *
     * @return Collection<int, SectionTime>
     */
    public function eventsFor(Carbon $date): Collection
    {
        $weekday = strtolower($date->format('l'));
        $times = $this->timesByWeekday[$weekday] ?? collect();

        return collect($times)
            ->filter(function (SectionTime $t) use ($date) {
                $section = $t->section;
                if (! $section) {
                    return false;
                }
                if ($section->start_date && $date->lt($section->start_date)) {
                    return false;
                }
                if ($section->end_date && $date->gt($section->end_date)) {
                    return false;
                }

                return true;
            })
            ->merge($this->extraLessons[$date->toDateString()] ?? collect())
            // Earliest lesson first, then room 1 through to the last one.
            ->sortBy(fn (SectionTime $t): string => self::slotKey($t))
            ->values();
    }

    /**
     * Extra and make-up lessons in the period on screen, keyed by `Y-m-d` — the
     * lessons that exist as rows rather than as a weekly pattern, and so have
     * no timetable row for `eventsFor()` to find.
     *
     * Loaded once per render, under the same filters as the timetable rows. The
     * room filter is the exception: a hand-entered lesson inherits its room
     * from the section's usual slot at best, so filtering by room would show it
     * in a room nobody booked for it.
     *
     * @return array<string, Collection<int, SectionTime>>
     */
    public function getExtraLessonsProperty(): array
    {
        if ($this->filters['room_id'] ?? null) {
            return [];
        }

        $sectionIds = Section::query()
            ->when($this->filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($this->filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($this->filters['section_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->pluck('id')
            ->all();

        return SectionScheduleService::extraLessonsBetween(
            $sectionIds,
            $this->periodStart(),
            $this->periodEnd(),
        );
    }

    /**
     * The period on screen as a printable weekly timetable: one page per week,
     * rooms down the side in walking order (1 → last) and the seven days
     * across, each cell listing that room's lessons earliest-first.
     *
     * The spreadsheet export answers "give me the data"; this answers "print
     * the schedule and pin it to the wall", which is a different shape — a
     * flat list of 60 lessons is not something anyone reads off a corkboard.
     */
    public function exportWeeklyPdf(): ?StreamedResponse
    {
        $weeks = $this->weeklyGrid();

        if ($weeks === []) {
            Notification::make()
                ->warning()
                ->title(__('No records found'))
                ->send();

            return null;
        }

        $html = View::make('pdf.weekly-schedule', [
            'logo' => self::embeddedLogo(),
            'weeks' => $weeks,
            'dayLabels' => self::weekdayLabels(),
            'filters' => $this->activeFilterLabels(),
            'printedAt' => now(),
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, PdfFonts::config());

        $content = $pdf->output();

        return response()->streamDownload(
            fn () => print ($content),
            'weekly-schedule-'.$this->periodStart()->toDateString().'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * The period broken into weeks, each week a room × weekday grid.
     *
     * Only rooms used somewhere in the period get a row, so a centre with
     * thirty rooms and four in use does not print twenty-six empty bands. The
     * "no room set" row is appended last, and only when something needs it.
     *
     * @return list<array{start: Carbon, end: Carbon, days: list<Carbon>, rooms: list<array{label: string, cells: array<string, list<array{time: string, section: string, subject: ?string, trainer: ?string, branch: ?string, color: ?string}>>}>}>
     */
    public function weeklyGrid(): array
    {
        $unassigned = __('No room set');

        // Walk the whole period once: which rooms are in play, and what sits in
        // each (room, day) box.
        $byWeek = [];
        $roomsUsed = [];

        foreach ($this->calendarDays as $day) {
            /** @var Carbon $date */
            $date = $day['date'];
            $weekKey = self::weekStart($date)->toDateString();

            foreach ($this->eventsFor($date) as $time) {
                $section = $time->section;
                $label = $time->room?->number
                    ? __('Room').' '.$time->room->number
                    : $unassigned;

                $roomsUsed[$label] = $time->room?->number !== null
                    ? self::naturalKey((string) $time->room->number)
                    : '~';

                $byWeek[$weekKey][$label][$date->toDateString()][] = [
                    // A hand-entered lesson may carry no times at all, and a
                    // null here would print as "now" rather than as unknown.
                    'time' => $time->start_time
                        ? Carbon::parse($time->start_time)->format('H:i')
                            .' – '.Carbon::parse($time->end_time ?: $time->start_time)->format('H:i')
                        : __('No time set'),
                    'section' => (string) ($section?->name ?? '—'),
                    'subject' => $section?->subject?->getTranslation('name', app()->getLocale(), false),
                    'trainer' => $section?->trainer?->getTranslation('name', app()->getLocale(), false),
                    'branch' => $section?->branch?->name,
                    'color' => $section?->subject?->color,
                ];
            }
        }

        if ($byWeek === []) {
            return [];
        }

        asort($roomsUsed, SORT_STRING);
        $roomOrder = array_keys($roomsUsed);

        $weeks = [];

        foreach ($byWeek as $weekKey => $rowsByRoom) {
            $start = Carbon::parse($weekKey);
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $days[] = $start->copy()->addDays($i);
            }

            $rooms = [];

            foreach ($roomOrder as $label) {
                $cells = [];

                foreach ($days as $date) {
                    $cells[$date->toDateString()] = $rowsByRoom[$label][$date->toDateString()] ?? [];
                }

                $rooms[] = ['label' => $label, 'cells' => $cells];
            }

            $weeks[] = [
                'start' => $start,
                'end' => $start->copy()->addDays(6),
                'days' => $days,
                'rooms' => $rooms,
            ];
        }

        return $weeks;
    }

    /** @return array<string, string> weekday key => translated name, Saturday-first. */
    public static function weekdayLabels(): array
    {
        return [
            'saturday' => __('Saturday'),
            'sunday' => __('Sunday'),
            'monday' => __('Monday'),
            'tuesday' => __('Tuesday'),
            'wednesday' => __('Wednesday'),
            'thursday' => __('Thursday'),
            'friday' => __('Friday'),
        ];
    }

    /**
     * The filters in force, as "Branch: X" strings for the printed header —
     * a schedule showing one room only must say so on the paper.
     *
     * @return list<string>
     */
    public function activeFilterLabels(): array
    {
        $locale = app()->getLocale();
        $labels = [];

        if ($branch = Branch::find($this->filters['branch_id'] ?? null)) {
            $labels[] = __('Branch').': '.$branch->name;
        }

        if ($subject = Subject::find($this->filters['subject_id'] ?? null)) {
            $labels[] = __('Course').': '.$subject->getTranslation('name', $locale, false);
        }

        if ($section = Section::find($this->filters['section_id'] ?? null)) {
            $labels[] = __('Section').': '.$section->name;
        }

        if ($room = Room::find($this->filters['room_id'] ?? null)) {
            $labels[] = __('Room').': '.$room->number;
        }

        return $labels;
    }

    /**
     * The centre logo as a data URI, or null when the file is missing. mPDF
     * resolves `src` against its own working directory, so it is inlined.
     */
    private static function embeddedLogo(): ?string
    {
        $file = public_path('images/light/android-chrome-512x512.png');

        if (! is_file($file)) {
            return null;
        }

        return 'data:'.(mime_content_type($file) ?: 'image/png')
            .';base64,'.base64_encode((string) file_get_contents($file));
    }

    /**
     * Stream the period on screen as a spreadsheet — one row per lesson, in
     * date then time order, carrying the same facts the cells show. The export
     * follows the view, so switching to a week and exporting gives that week.
     */
    public function exportPeriod(): StreamedResponse
    {
        $locale = app()->getLocale();
        $rows = [];

        foreach ($this->calendarDays as $day) {
            /** @var Carbon $date */
            $date = $day['date'];

            foreach ($this->eventsFor($date) as $time) {
                $section = $time->section;

                $rows[] = [
                    $date->toDateString(),
                    __(ucfirst(strtolower($date->format('l')))),
                    $time->start_time
                        ? substr((string) $time->start_time, 0, 5).' - '.substr((string) ($time->end_time ?: $time->start_time), 0, 5)
                        : __('No time set'),
                    $time->extra_session_type
                        ? SectionSession::extraLabelFor($time->extra_session_type)
                        : __('Regular Session'),
                    (string) ($section?->name ?? '—'),
                    (string) ($section?->subject?->getTranslation('name', $locale, false) ?? '—'),
                    (string) ($section?->trainer?->getTranslation('name', $locale, false) ?? '—'),
                    (string) ($section?->branch?->name ?? '—'),
                    $time->room?->number ? __('Room').' '.$time->room->number : __('No room set'),
                ];
            }
        }

        $filename = 'calendar-'.$this->periodStart()->toDateString().'-to-'.$this->periodEnd()->toDateString().'.xlsx';

        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                __('Date'),
                __('Day'),
                __('Time'),
                __('Session Type'),
                __('Section'),
                __('Course'),
                __('Trainer'),
                __('Branch'),
                __('Room'),
            ]));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
