<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecords extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected string $view = 'filament.admin.pages.attendance-records';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

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

    /** Either the flat record list, or the per-section sheet. */
    public string $viewMode = 'records';

    /** Section shown in the sheet view. */
    public ?int $sheetSectionId = null;

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
     * The whole attendance history of the selected section as a grid: one row
     * per student, one column per date that actually has attendance recorded.
     *
     * @return array{dates: list<string>, rows: list<array{student: Student, cells: array<string, string|null>, counts: array<string, int>, rate: float}>, columnTotals: array<string, array<string, int>>}
     */
    #[Computed]
    public function sheet(): array
    {
        $empty = ['dates' => [], 'rows' => [], 'columnTotals' => []];

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

        $dates = $records
            ->map(fn (Attendance $a): string => $a->date->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

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
                $cells[$date] = $attendance->status;

                if (isset($counts[$attendance->status])) {
                    $counts[$attendance->status]++;
                    $columnTotals[$date][$attendance->status]++;
                }
            }

            $recorded = array_sum($counts);

            $rows[] = [
                'student' => $student,
                'cells' => $cells,
                'counts' => $counts,
                'rate' => $recorded > 0
                    ? round((($counts['present'] + $counts['late']) / $recorded) * 100, 1)
                    : 0.0,
            ];
        }

        return ['dates' => $dates, 'rows' => $rows, 'columnTotals' => $columnTotals];
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

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Attendance::query()->with(['student', 'section.subject', 'session', 'recordedBy', 'updatedBy'])
            )
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->state(fn (Attendance $record): string => self::translated($record->student?->name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->state(fn (Attendance $record): string => self::translated($record->section?->name)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'late' => 'warning',
                        'excused' => 'info',
                        'absent' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_makeup')
                    ->label(__('Makeup'))
                    ->boolean(),
                TextColumn::make('session.type')
                    ->label(__('Session Type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (\App\Models\SectionSession::typeOptions()[$state] ?? $state)
                        : '—')
                    ->toggleable(),
                TextColumn::make('recorded_by')
                    ->label(__('Recorded By'))
                    ->state(fn (Attendance $record): ?string => $record->actorName())
                    ->toggleable(),
                TextColumn::make('recorded_at')
                    ->label(__('Recorded At'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Last Modified'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->options(fn () => Section::all()->mapWithKeys(fn (Section $s) => [
                        $s->id => self::translated($s->name),
                    ])->toArray())
                    ->searchable(),
                SelectFilter::make('student_id')
                    ->label(__('Student'))
                    ->options(fn () => Student::all()->mapWithKeys(fn (Student $s) => [
                        $s->id => self::translated($s->name),
                    ])->toArray())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(self::statusLabels()),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label(__('Export to Excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn (): StreamedResponse => $this->exportExcel()),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('No records found'))
            ->defaultSort('date', 'desc');
    }

    /** Stream an XLSX of the currently filtered attendance rows. */
    public function exportExcel(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery() ?? $this->getFilteredTableQuery();
        $rows = $query->with(['student', 'section'])->get();
        $labels = self::statusLabels();

        return response()->streamDownload(function () use ($rows, $labels): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                __('Date'),
                __('Student'),
                __('Section'),
                __('Status'),
                __('Makeup'),
                __('Note'),
            ]));

            foreach ($rows as $a) {
                $writer->addRow(Row::fromValues([
                    $a->date?->format('Y-m-d'),
                    self::translated($a->student?->name),
                    self::translated($a->section?->name),
                    $labels[$a->status] ?? $a->status,
                    $a->is_makeup ? __('Yes') : __('No'),
                    (string) ($a->note ?? ''),
                ]));
            }

            $writer->close();
        }, 'attendance-'.now()->format('Y-m-d-Hi').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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

        return response()->streamDownload(function () use ($sheet, $labels): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                '#',
                __('Student'),
                __('Student Number'),
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
        }, 'attendance-sheet-'.$section->id.'-'.now()->format('Y-m-d-Hi').'.xlsx', [
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
