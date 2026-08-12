<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecords extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected string $view = 'filament.admin.pages.attendance-records';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('Attendance Records');
    }

    public function getTitle(): string
    {
        return __('Attendance Records');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('attendance.index') ?? false;
    }

    /**
     * The centre counts a "month" as a fixed number of lessons, not calendar
     * weeks — a section that meets twice a week still bills a month every 12
     * sessions, so the sheet is paged the same way.
     */
    public const SESSIONS_PER_MONTH = 12;

    /** Section shown in the sheet. */
    public ?int $sheetSectionId = null;

    /** Which block of :count sessions is on screen, 1-based. */
    public int $monthIndex = 1;

    /** Reset to the first month whenever the section changes. */
    public function updatedSheetSectionId(): void
    {
        $this->monthIndex = 1;
        unset($this->sheet);
    }

    public function goToMonth(int $index): void
    {
        $this->monthIndex = max(1, $index);
        unset($this->sheet);
    }

    /** @return array<int, string> section id => label */
    public function sectionOptions(): array
    {
        return Section::query()
            ->with('subject')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Section $section): array => [
                $section->id => self::translated($section->name)
                    .($section->subject ? ' — '.self::translated($section->subject->name) : ''),
            ])
            ->all();
    }

    /**
     * One month of the selected section's attendance as a grid: one row per
     * student, one column per recorded date inside that month.
     *
     * Counts and the rate cover the visible month only — that is the whole
     * point of paging it, so a student's standing this month is not diluted by
     * a good or bad month six lessons ago.
     *
     * @return array{dates: list<string>, rows: list<array{student: Student, phone: ?string, financial_status: ?string, cells: array<string, string|null>, counts: array<string, int>, rate: float}>, columnTotals: array<string, array<string, int>>, months: int, month: int, allDates: int}
     */
    #[Computed]
    public function sheet(): array
    {
        $empty = ['dates' => [], 'rows' => [], 'columnTotals' => [], 'months' => 0, 'month' => 1, 'allDates' => 0];

        if (! $this->sheetSectionId) {
            return $empty;
        }

        $records = Attendance::query()
            ->where('section_id', $this->sheetSectionId)
            ->orderBy('date')
            ->get();

        if ($records->isEmpty()) {
            return $empty;
        }

        $allDates = $records
            ->map(fn (Attendance $a): string => $a->date->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $chunks = array_chunk($allDates, self::SESSIONS_PER_MONTH);
        $months = count($chunks);
        $month = min(max(1, $this->monthIndex), $months);
        $dates = $chunks[$month - 1];

        // The roster drives the row order, but keep students who have records
        // here and were since moved out of the section — their history is
        // still part of the sheet.
        $rosterIds = Registration::query()
            ->where('section_id', $this->sheetSectionId)
            ->orderBy('id')
            ->pluck('student_id');

        $studentIds = $rosterIds
            ->merge($records->pluck('student_id'))
            ->unique()
            ->values();

        $students = Student::query()
            ->withTrashed()
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        // Financial standing is per (student, section) — the registration that
        // put them in this sheet, not their overall balance.
        $financialStatuses = Registration::query()
            ->where('section_id', $this->sheetSectionId)
            ->pluck('financial_status', 'student_id');

        $byStudent = $records->groupBy('student_id');
        $columnTotals = array_fill_keys($dates, ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0]);
        $rows = [];

        foreach ($studentIds as $studentId) {
            $student = $students->get($studentId);

            if (! $student) {
                continue;
            }

            $cells = array_fill_keys($dates, null);
            $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

            foreach ($byStudent->get($studentId, collect()) as $attendance) {
                $date = $attendance->date->toDateString();

                // Outside the visible month — skip it entirely so it neither
                // shows nor counts.
                if (! array_key_exists($date, $cells)) {
                    continue;
                }

                $cells[$date] = $attendance->status;

                if (isset($counts[$attendance->status])) {
                    $counts[$attendance->status]++;
                    $columnTotals[$date][$attendance->status]++;
                }
            }

            $recorded = array_sum($counts);

            $rows[] = [
                'student' => $student,
                'phone' => $student->phone_number ?: $student->whatsapp_number,
                'financial_status' => $financialStatuses[$studentId] ?? null,
                'cells' => $cells,
                'counts' => $counts,
                'rate' => $recorded > 0
                    ? round((($counts['present'] + $counts['late']) / $recorded) * 100, 1)
                    : 0.0,
            ];
        }

        return [
            'dates' => $dates,
            'rows' => $rows,
            'columnTotals' => $columnTotals,
            'months' => $months,
            'month' => $month,
            'allDates' => count($allDates),
        ];
    }

    /** @return array<string, string> */
    public static function financialStatusLabels(): array
    {
        return [
            'ok' => __('Paid'),
            'warning' => __('Payment due soon'),
            'due' => __('Payment Due'),
            'overdue' => __('Overdue'),
        ];
    }

    public function selectedSection(): ?Section
    {
        return $this->sheetSectionId
            ? Section::with(['subject', 'trainer', 'branch'])->find($this->sheetSectionId)
            : null;
    }

    /** Single-letter cell marker, so a 30-column sheet still fits on screen. */
    public static function statusInitial(string $status): string
    {
        return mb_substr(self::statusLabels()[$status] ?? $status, 0, 1);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'present' => __('Present'),
            'absent' => __('Absent'),
            'late' => __('Late'),
            'excused' => __('Excused'),
        ];
    }

    /** Stream the sheet view — students down, dates across — as XLSX. */
    public function exportSheet(): ?StreamedResponse
    {
        $sheet = $this->sheet;
        $section = $this->selectedSection();

        if (! $section || $sheet['dates'] === []) {
            Notification::make()
                ->warning()
                ->title(__('No records found'))
                ->send();

            return null;
        }

        $labels = self::statusLabels();
        $financialLabels = self::financialStatusLabels();

        return response()->streamDownload(function () use ($sheet, $labels, $financialLabels): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                '#',
                __('Student'),
                __('Student Number'),
                __('Phone'),
                __('Financial Status'),
                ...$sheet['dates'],
                __('Present'),
                __('Absent'),
                __('Late'),
                __('Excused'),
                __('Attendance Rate'),
            ]));

            foreach ($sheet['rows'] as $index => $row) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    self::translated($row['student']->name),
                    (string) ($row['student']->student_number ?? ''),
                    (string) ($row['phone'] ?? ''),
                    $row['financial_status'] ? ($financialLabels[$row['financial_status']] ?? $row['financial_status']) : '',
                    ...array_map(
                        fn (?string $status): string => $status ? ($labels[$status] ?? $status) : '',
                        array_values($row['cells'])
                    ),
                    $row['counts']['present'],
                    $row['counts']['absent'],
                    $row['counts']['late'],
                    $row['counts']['excused'],
                    $row['rate'].'%',
                ]));
            }

            $writer->close();
            // The file covers only the month on screen, so the name says which.
        }, 'attendance-sheet-'.$section->id.'-m'.$sheet['month'].'-'.now()->format('Y-m-d-Hi').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Resolve a possibly-translatable name value to the current locale string. */
    private static function translated(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? reset($value) ?: '—');
        }

        return (string) ($value ?? '—');
    }
}
