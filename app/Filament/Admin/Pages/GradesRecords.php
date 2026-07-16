<?php

namespace App\Filament\Admin\Pages;

use App\Models\ExamGrade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Trainer;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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

class GradesRecords extends Page implements HasTable
{
    use HasHexaLite, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected string $view = 'filament.admin.pages.grades-records';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('All Grades');
    }

    public function getTitle(): string
    {
        return __('All Grades');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('exam.index');
    }

    public function roleName(): string
    {
        return __('All Grades');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ExamGrade::query()->with(['student', 'exam.section.subject', 'exam.section.trainer'])
            )
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->state(fn (ExamGrade $record): string => self::translated($record->student?->name))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.student_number')
                    ->label(__('Student Number'))
                    ->searchable(),
                TextColumn::make('exam.name')
                    ->label(__('Exam'))
                    ->searchable(),
                TextColumn::make('exam.section.name')
                    ->label(__('Section'))
                    ->state(fn (ExamGrade $record): string => self::translated($record->exam?->section?->name)),
                TextColumn::make('exam.section.subject.name')
                    ->label(__('Subject'))
                    ->state(fn (ExamGrade $record): string => self::translated($record->exam?->section?->subject?->name)),
                TextColumn::make('exam.section.trainer.name')
                    ->label(__('Trainer'))
                    ->state(fn (ExamGrade $record): string => self::translated($record->exam?->section?->trainer?->name)),
                TextColumn::make('exam.date')
                    ->label(__('Date'))
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('score')
                    ->label(__('Score'))
                    ->state(fn (ExamGrade $record): string => rtrim(rtrim((string) $record->score, '0'), '.')
                        .' / '.rtrim(rtrim((string) ($record->exam?->max_score ?? ''), '0'), '.'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('trainer')
                    ->label(__('Trainer'))
                    ->options(fn () => Trainer::all()->mapWithKeys(fn (Trainer $t) => [
                        $t->id => self::translated($t->name),
                    ])->toArray())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('exam.section', fn (Builder $q) => $q->where('trainer_id', $data['value']))
                        : $query)
                    ->searchable(),
                SelectFilter::make('section')
                    ->label(__('Section'))
                    ->options(fn () => Section::all()->mapWithKeys(fn (Section $s) => [
                        $s->id => self::translated($s->name),
                    ])->toArray())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('exam', fn (Builder $q) => $q->where('section_id', $data['value']))
                        : $query)
                    ->searchable(),
                SelectFilter::make('student_id')
                    ->label(__('Student'))
                    ->options(fn () => Student::all()->mapWithKeys(fn (Student $s) => [
                        $s->id => self::translated($s->name),
                    ])->toArray())
                    ->searchable(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereHas('exam', fn (Builder $e) => $e->whereDate('date', '>=', $date)))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereHas('exam', fn (Builder $e) => $e->whereDate('date', '<=', $date)));
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
            ->emptyStateHeading(__('No records found'))
            ->defaultSort('id', 'desc');
    }

    /** Stream an XLSX of the currently filtered grade rows. */
    public function exportExcel(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery() ?? $this->getFilteredTableQuery();
        $rows = $query->with(['student', 'exam.section.subject', 'exam.section.trainer'])->get();

        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                __('Student'),
                __('Student Number'),
                __('Exam'),
                __('Section'),
                __('Subject'),
                __('Trainer'),
                __('Date'),
                __('Score'),
                __('Max Score'),
                __('Note'),
            ]));

            foreach ($rows as $g) {
                $writer->addRow(Row::fromValues([
                    self::translated($g->student?->name),
                    (string) ($g->student?->student_number ?? ''),
                    (string) ($g->exam?->name ?? ''),
                    self::translated($g->exam?->section?->name),
                    self::translated($g->exam?->section?->subject?->name),
                    self::translated($g->exam?->section?->trainer?->name),
                    $g->exam?->date?->format('Y-m-d'),
                    (float) $g->score,
                    (float) ($g->exam?->max_score ?? 0),
                    (string) ($g->note ?? ''),
                ]));
            }

            $writer->close();
        }, 'grades-'.now()->format('Y-m-d-Hi').'.xlsx', [
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
