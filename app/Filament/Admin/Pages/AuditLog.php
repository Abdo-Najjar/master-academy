<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Support\ExportsTableRecords;
use App\Models\Attendance;
use App\Models\ExamGrade;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only view of every recorded change (payments, attendance, grades,
 * student data, trainer rates, sections) with the old value, the new value and
 * the operator's stated reason. Entries can never be edited or deleted from
 * here — the log is append-only by design.
 */
class AuditLog extends Page implements HasTable
{
    use ExportsTableRecords, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected string $view = 'filament.admin.pages.audit-log';

    protected static ?int $navigationSort = 3;

    /**
     * Models worth auditing, keyed by class. Anything else still shows up but
     * under its raw class basename.
     *
     * @return array<class-string, string>
     */
    public static function subjectTypes(): array
    {
        return [
            Registration::class => __('Registration'),
            Attendance::class => __('Attendance'),
            ExamGrade::class => __('Grade'),
            Student::class => __('Student'),
            Trainer::class => __('Trainer'),
            Section::class => __('Section'),
            SectionSession::class => __('Session'),
            User::class => __('Administrator'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('Audit Log');
    }

    public function getTitle(): string
    {
        return __('Audit Log');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('audit_log.index') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [$this->tableExportAction()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label(__('Record'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (self::subjectTypes()[$state] ?? __(class_basename($state)))
                        : '—')
                    ->description(fn (Activity $record): ?string => $record->subject_id ? '#'.$record->subject_id : null),
                TextColumn::make('event')
                    ->label(__('Action'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'created' => __('Created'),
                        'updated' => __('Updated'),
                        'deleted' => __('Deleted'),
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('causer.name')
                    ->label(__('By'))
                    ->placeholder(__('System'))
                    ->searchable(),
                TextColumn::make('changes_summary')
                    ->label(__('Changes'))
                    ->state(fn (Activity $record): string => self::summarize($record))
                    ->wrap()
                    ->tooltip(fn (Activity $record): string => self::summarize($record)),
                TextColumn::make('reason')
                    ->label(__('Reason for change'))
                    ->state(fn (Activity $record) => $record->properties['reason'] ?? null)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label(__('Record'))
                    ->options(self::subjectTypes()),
                SelectFilter::make('event')
                    ->label(__('Action'))
                    ->options([
                        'created' => __('Created'),
                        'updated' => __('Updated'),
                        'deleted' => __('Deleted'),
                    ]),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('Details'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(__('Change details'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (Activity $record): Htmlable => self::detailsTable($record)),
            ])
            ->emptyStateHeading(__('No records found'))
            ->defaultSort('created_at', 'desc');
    }

    /** One-line "field: old → new" summary of what changed. */
    public static function summarize(Activity $activity): string
    {
        $attributes = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);

        if ($attributes === []) {
            return '—';
        }

        $parts = [];

        foreach ($attributes as $field => $new) {
            $before = $old[$field] ?? null;
            $parts[] = $before === null || $before === ''
                ? sprintf('%s: %s', $field, self::stringify($new))
                : sprintf('%s: %s → %s', $field, self::stringify($before), self::stringify($new));
        }

        return implode(' | ', $parts);
    }

    protected static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return (string) ($value ?? '—');
    }

    /** Old / new value table shown in the details modal. */
    protected static function detailsTable(Activity $activity): Htmlable
    {
        $attributes = (array) ($activity->properties['attributes'] ?? []);
        $old = (array) ($activity->properties['old'] ?? []);
        $reason = $activity->properties['reason'] ?? null;

        $rows = '';
        foreach ($attributes as $field => $new) {
            $rows .= sprintf(
                '<tr><td class="py-1 pe-4 font-medium">%s</td><td class="py-1 pe-4 text-danger-600">%s</td><td class="py-1 text-success-600">%s</td></tr>',
                e($field),
                e(self::stringify($old[$field] ?? null)),
                e(self::stringify($new)),
            );
        }

        if ($rows === '') {
            $rows = sprintf('<tr><td colspan="3" class="py-1">%s</td></tr>', e(__('No records found')));
        }

        return new HtmlString(sprintf(
            '<div class="text-sm space-y-3">%s<table class="w-full text-start"><thead><tr>'
            .'<th class="text-start pb-2">%s</th><th class="text-start pb-2">%s</th><th class="text-start pb-2">%s</th>'
            .'</tr></thead><tbody>%s</tbody></table></div>',
            $reason ? sprintf('<p><span class="font-medium">%s:</span> %s</p>', e(__('Reason for change')), e($reason)) : '',
            e(__('Field')),
            e(__('Old Value')),
            e(__('New Value')),
            $rows,
        ));
    }
}
