<?php

namespace App\Filament\Admin\Resources\JoinApplications;

use App\Filament\Admin\Resources\JoinApplications\Pages\ManageJoinApplications;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\JoinApplication;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class JoinApplicationResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = JoinApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationGroup(): ?string
    {
        return __('Website');
    }

    public static function getModelLabel(): string
    {
        return __('Join Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Join Requests');
    }

    /** Badge the sidebar with the count of requests nobody has picked up yet. */
    public static function getNavigationBadge(): ?string
    {
        $pending = JoinApplication::pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function permissionPrefix(): string
    {
        return 'join_application';
    }

    public static function canCreate(): bool
    {
        // Requests only ever arrive from the public form, so there is no
        // `join_application.create` gate to grant.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Applicant'))
                    ->schema([
                        TextInput::make('full_name')
                            ->label(__('Full name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('phone')
                            ->label(__('Contact / WhatsApp number'))
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        TextInput::make('age')
                            ->label(__('Age'))
                            ->numeric(),
                        Select::make('gender')
                            ->label(__('Gender'))
                            ->options([
                                'male' => __('Male'),
                                'female' => __('Female'),
                            ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Request'))
                    ->schema([
                        Select::make('program_id')
                            ->label(__('Requested program'))
                            ->relationship('program', 'title')
                            ->searchable()
                            ->preload(),
                        TextInput::make('program_name')
                            ->label(__('Other program'))
                            ->maxLength(120),
                        Select::make('branch_id')
                            ->label(__('Suitable branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('contact_preference')
                            ->label(__('Preferred contact method'))
                            ->options(JoinApplication::contactPreferenceOptions())
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('Notes or questions (optional)'))
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Follow-up'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(JoinApplication::statusOptions())
                            ->required(),
                        Textarea::make('admin_notes')
                            ->label(__('Internal notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label(__('Request number:'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('full_name')
                    ->label(__('Full name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Contact / WhatsApp number'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('requested_program')
                    ->label(__('Requested program')),
                TextColumn::make('branch.name')
                    ->label(__('Suitable branch'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => JoinApplication::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'contacted' => 'info',
                        'enrolled' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(JoinApplication::statusOptions()),
                SelectFilter::make('branch_id')
                    ->label(__('Suitable branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('program_id')
                    ->label(__('Requested program'))
                    ->relationship('program', 'title')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(static::canUpdateRecords()),
                    static::whatsappAction(),
                    static::convertToStudentAction(),
                    DeleteAction::make()
                        ->visible(static::canDeleteRecords()),
                    ForceDeleteAction::make()
                        ->visible(static::canDeleteRecords()),
                    RestoreAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                    ForceDeleteBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                    RestoreBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Opens WhatsApp with the applicant's number and marks the request as contacted. */
    protected static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label(__('Chat on WhatsApp'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->url(fn (JoinApplication $record): string => 'https://wa.me/'.preg_replace('/\D+/', '', $record->phone))
            ->openUrlInNewTab()
            ->visible(fn (JoinApplication $record): bool => filled($record->phone) && static::allows('update'))
            ->after(function (JoinApplication $record): void {
                if ($record->status === 'new') {
                    $record->update([
                        'status' => 'contacted',
                        'handled_by' => auth()->id(),
                        'handled_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Turns an approved request into a real student record, carrying over the
     * details the applicant already gave so nobody retypes them.
     */
    protected static function convertToStudentAction(): Action
    {
        return Action::make('convertToStudent')
            ->label(__('Convert to Student'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('A student record will be created from this request, and the request will be marked as enrolled.'))
            // Needs both sides: permission to create the student, and permission
            // to update the request it marks as enrolled.
            ->visible(fn (JoinApplication $record): bool => $record->student_id === null
                && static::allows('update')
                && (auth()->user()?->can('student.create') ?? false))
            ->action(function (JoinApplication $record): void {
                $student = Student::create([
                    'name' => ['ar' => $record->full_name],
                    'phone_number' => $record->phone,
                    'whatsapp_number' => $record->phone,
                    'gender' => $record->gender,
                    'status' => 'active',
                    'is_active' => true,
                ]);

                $record->update([
                    'student_id' => $student->id,
                    'status' => 'enrolled',
                    'handled_by' => auth()->id(),
                    'handled_at' => now(),
                ]);

                Notification::make()
                    ->success()
                    ->title(__('Student created successfully'))
                    ->body($student->student_number)
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJoinApplications::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
