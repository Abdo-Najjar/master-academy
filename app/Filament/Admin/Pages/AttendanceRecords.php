<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Section;
use App\Models\Student;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecords extends Page implements HasTable
{
    use HasHexaLite, InteractsWithTable;

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
        return hexa()->can('attendance.index');
    }

    public function roleName(): string
    {
        return __('Attendance Records');
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
                Attendance::query()->with(['student', 'section.subject'])
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
                \Filament\Actions\Action::make('exportExcel')
                    ->label(__('Export to Excel'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn (): StreamedResponse => $this->exportExcel()),
            ])
            ->paginated([25, 50, 100])
            ->defaultSort('date', 'desc');
    }

    /** Stream an XLSX of the currently filtered attendance rows. */
    public function exportExcel(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery() ?? $this->getFilteredTableQuery();
        $rows = $query->with(['student', 'section'])->get();
        $labels = self::statusLabels();

        return response()->streamDownload(function () use ($rows, $labels): void {
            $writer = new Writer();
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

    /** Resolve a possibly-translatable name value to the current locale string. */
    private static function translated(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value[app()->getLocale()] ?? reset($value) ?: '—');
        }

        return (string) ($value ?? '—');
    }
}
