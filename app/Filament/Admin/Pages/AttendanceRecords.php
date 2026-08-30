<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Services\FinancialDueService;
use App\Support\PdfFonts;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
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
     * The centre counts a "month" as a number of lessons, not calendar weeks —
     * a section that meets twice a week still bills a month every N sessions,
     * so the sheet is paged the same way. N comes from the section's own
     * `sessions_per_cycle`; this is only the fallback for sections that never
     * set one.
     */
    public const SESSIONS_PER_MONTH = 12;

    /** Page size for the section on screen, in lessons. */
    public function sessionsPerMonth(): int
    {
        return $this->selectedSection()?->sessionsPerMonth(self::SESSIONS_PER_MONTH)
            ?? self::SESSIONS_PER_MONTH;
    }

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
     * @return array{dates: list<string>, rows: list<array{student: Student, phone: ?string, financial_status: ?string, paid: ?float, remaining: ?float, cells: array<string, string|null>, counts: array<string, int>, rate: float}>, columnTotals: array<string, array<string, int>>, months: int, month: int, allDates: int, perMonth: int}
     */
    #[Computed]
    public function sheet(): array
    {
        $empty = ['dates' => [], 'rows' => [], 'columnTotals' => [], 'months' => 0, 'month' => 1, 'allDates' => 0, 'perMonth' => $this->sessionsPerMonth()];

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

        $perMonth = $this->sessionsPerMonth();

        $chunks = array_chunk($allDates, $perMonth);
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
        // put them in this sheet, not their overall balance. The amounts ride
        // along with the status: "paid" on its own never said paid *how much*,
        // which is the first thing the desk is asked.
        $registrations = Registration::query()
            ->where('section_id', $this->sheetSectionId)
            ->get()
            ->keyBy('student_id');

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
            $registration = $registrations->get($studentId);

            $rows[] = [
                'student' => $student,
                'phone' => $student->phone_number ?: $student->whatsapp_number,
                'financial_status' => $registration?->financial_status,
                // Money actually collected against this registration, and what
                // is still owed on it.
                'paid' => $registration ? (float) $registration->funded_amount : null,
                'remaining' => $registration ? FinancialDueService::remainingBalance($registration) : null,
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
            'perMonth' => $perMonth,
        ];
    }

    /**
     * "Paid 40 · Remaining 20" for a sheet row — or just "Paid 60" once the
     * registration is settled. Null when the student has no registration in
     * this section (they only appear because of old attendance records).
     *
     * @param  array{paid: ?float, remaining: ?float}  $row
     */
    public static function financialAmounts(array $row): ?string
    {
        if ($row['paid'] === null) {
            return null;
        }

        $money = fn (float $amount): string => number_format($amount, 0).' ₪';

        $line = __('Paid').' '.$money($row['paid']);

        if ($row['remaining'] > 0.009) {
            $line .= ' · '.__('Remaining').' '.$money((float) $row['remaining']);
        }

        return $line;
    }

    /** @return array<string, string> */
    public static function financialStatusLabels(): array
    {
        return [
            'ok' => __('Settled'),
            'warning' => __('Payment due soon'),
            'due' => __('Payment Due'),
            'overdue' => __('Overdue'),
        ];
    }

    /** @var array{id: int, section: ?Section}|null */
    protected ?array $sectionCache = null;

    public function selectedSection(): ?Section
    {
        if (! $this->sheetSectionId) {
            return null;
        }

        // Asked for by the sheet, the page size and the view within one render.
        if ($this->sectionCache && $this->sectionCache['id'] === $this->sheetSectionId) {
            return $this->sectionCache['section'];
        }

        $section = Section::with(['subject', 'trainer', 'branch'])->find($this->sheetSectionId);

        $this->sectionCache = ['id' => $this->sheetSectionId, 'section' => $section];

        return $section;
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

    /**
     * The same grid the page shows, as a PDF — students down, dates across,
     * with the per-student tallies and the footer totals. Landscape, because
     * a month of sessions plus the tally columns does not fit portrait.
     */
    public function exportSheetPdf(): ?StreamedResponse
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

        $grandTotals = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        foreach ($sheet['rows'] as $row) {
            foreach ($grandTotals as $bucket => $_) {
                $grandTotals[$bucket] += $row['counts'][$bucket];
            }
        }

        $grandRecorded = array_sum($grandTotals);
        $sectionName = self::translated($section->name);

        $html = View::make('pdf.attendance-records-sheet', [
            'section' => $section,
            'logo' => self::embeddedLogo(),
            'sectionName' => $sectionName,
            'subjectName' => $section->subject ? self::translated($section->subject->name) : null,
            'trainerName' => $section->trainer ? self::translated($section->trainer->name) : null,
            'sheet' => $sheet,
            'labels' => self::statusLabels(),
            'financialLabels' => self::financialStatusLabels(),
            'grandTotals' => $grandTotals,
            'overallRate' => $grandRecorded > 0
                ? round((($grandTotals['present'] + $grandTotals['late']) / $grandRecorded) * 100, 1)
                : 0.0,
            'printedAt' => now(),
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, PdfFonts::config());

        $content = $pdf->output();

        return response()->streamDownload(
            fn () => print ($content),
            'attendance-sheet-'.(Str::slug($sectionName) ?: $section->id).'-m'.$sheet['month'].'-'.now()->format('Y-m-d-Hi').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * The centre logo as a data URI, or null when the file is missing.
     *
     * mPDF fetches `src` paths relative to its own working directory, so the
     * image is inlined instead — the same trick the receipt and card PDFs use.
     * The sheet prints on white, so the light-theme mark is the right one.
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

    /** Resolve a possibly-translatable name value to the current locale string. */
    private static function translated(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? reset($value) ?: '—');
        }

        return (string) ($value ?? '—');
    }
}
