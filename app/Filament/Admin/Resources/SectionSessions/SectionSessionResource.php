<?php

namespace App\Filament\Admin\Resources\SectionSessions;

use App\Filament\Admin\Resources\SectionSessions\Pages\ManageSectionSessions;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Services\SessionBillingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SectionSessionResource extends Resource
{
    protected static ?string $model = SectionSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'date';

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Session');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sessions');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('section_session.index') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(fn () => Section::query()
                                ->with('subject')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (Section $s) => [
                                    $s->id => $s->name.($s->subject
                                        ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false)
                                        : ''),
                                ]))
                            ->searchable()
                            ->required(),
                        DatePicker::make('date')
                            ->label(__('Date'))
                            ->native(false)
                            ->default(now())
                            ->required(),
                        TimePicker::make('start_time')
                            ->label(__('Start Time'))
                            ->seconds(false),
                        TimePicker::make('end_time')
                            ->label(__('End Time'))
                            ->seconds(false),
                    ])
                    ->columns(1),

                FormSection::make('')
                    ->schema([
                        Select::make('type')
                            ->label(__('Session Type'))
                            ->options(SectionSession::typeOptions())
                            ->default(SectionSession::TYPE_REGULAR)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('counts_toward_billing', $state !== SectionSession::TYPE_PRIVATE);
                            })
                            ->helperText(__('Makeup sessions count toward the payment cycle; private sessions are billed separately and never do.')),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(SectionSession::statusOptions())
                            ->default(SectionSession::STATUS_HELD)
                            ->required()
                            ->live(),
                        TextInput::make('cancellation_reason')
                            ->label(__('Cancellation Reason'))
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('status') === SectionSession::STATUS_CANCELLED)
                            ->visible(fn (Get $get): bool => $get('status') === SectionSession::STATUS_CANCELLED),
                        TextInput::make('fee')
                            ->label(__('Private Session Fee'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('₪')
                            ->visible(fn (Get $get): bool => $get('type') === SectionSession::TYPE_PRIVATE),
                        TextInput::make('trainer_rate')
                            ->label(__('Trainer Rate (%)'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText(__('Leave empty to use the section rate'))
                            ->visible(fn (Get $get): bool => $get('type') === SectionSession::TYPE_PRIVATE),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->sortable(),
                TextColumn::make('section.trainer.name')->label(__('Trainer'))->toggleable(),
                TextColumn::make('type')
                    ->label(__('Session Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SectionSession::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        SectionSession::TYPE_MAKEUP => 'warning',
                        SectionSession::TYPE_PRIVATE => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SectionSession::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        SectionSession::STATUS_HELD => 'success',
                        SectionSession::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('cancellation_reason')->label(__('Cancellation Reason'))->limit(30)->toggleable(),
                IconColumn::make('counted_at_billing')
                    ->label(__('Counted'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('fee')->label(__('Private Session Fee'))->money('ILS')->toggleable(),
                TextColumn::make('creator.name')->label(__('Recorded By'))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')->label(__('Session Type'))->options(SectionSession::typeOptions()),
                SelectFilter::make('status')->label(__('Status'))->options(SectionSession::statusOptions()),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label(__('From'))->native(false),
                        DatePicker::make('until')->label(__('To'))->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::cancelAction(),
                    self::chargePrivateAction(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    /** Cancel a lesson, recording why — the counter is rolled back automatically. */
    public static function cancelAction(): Action
    {
        return Action::make('cancelSession')
            ->label(__('Cancel Session'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (?SectionSession $record): bool => $record !== null
                && ! $record->isCancelled()
                && (auth()->user()?->can('section_session.update') ?? false))
            ->schema([
                TextInput::make('cancellation_reason')
                    ->label(__('Cancellation Reason'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (SectionSession $record, array $data): void {
                $record->update([
                    'status' => SectionSession::STATUS_CANCELLED,
                    'cancellation_reason' => $data['cancellation_reason'],
                ]);

                Notification::make()->success()->title(__('Session cancelled'))->send();
            });
    }

    /** Charge a private lesson's own fee to the chosen students. */
    public static function chargePrivateAction(): Action
    {
        return Action::make('chargePrivate')
            ->label(__('Charge Private Session Fee'))
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn (?SectionSession $record): bool => $record !== null
                && $record->isPrivate()
                && (float) $record->fee > 0
                && (auth()->user()?->can('section_session.charge') ?? false))
            ->schema(fn (SectionSession $record): array => [
                CheckboxList::make('student_ids')
                    ->label(__('Students'))
                    ->options(fn (): array => Registration::query()
                        ->where('section_id', $record->section_id)
                        ->with('student')
                        ->get()
                        ->mapWithKeys(fn (Registration $r) => [
                            $r->student_id => $r->student?->getTranslation('name', app()->getLocale(), false) ?? '#'.$r->student_id,
                        ])
                        ->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->required(),
            ])
            ->action(function (SectionSession $record, array $data): void {
                $count = SessionBillingService::chargePrivateSession($record, array_map('intval', $data['student_ids'] ?? []));

                Notification::make()
                    ->success()
                    ->title(__(':count student(s) charged', ['count' => $count]))
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSectionSessions::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
