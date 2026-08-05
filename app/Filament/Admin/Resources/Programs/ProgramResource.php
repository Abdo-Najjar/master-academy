<?php

namespace App\Filament\Admin\Resources\Programs;

use App\Filament\Admin\Resources\Programs\Pages\ManagePrograms;
use App\Filament\Support\AuthorizesResourceActions;
use App\Filament\Support\DeletionGuard;
use App\Filament\Support\TranslatableInput;
use App\Models\Program;
use BackedEnum;
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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ProgramResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Program::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Website');
    }

    public static function getModelLabel(): string
    {
        return __('Program');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Programs');
    }

    public static function permissionPrefix(): string
    {
        return 'program';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Program Details'))
                    ->schema([
                        TranslatableInput::make('title', __('Title')),
                        TranslatableInput::textarea('description', __('Description'), required: false),
                        Select::make('category')
                            ->label(__('Category'))
                            ->options(Program::categoryOptions())
                            ->default('professional')
                            ->required(),
                        TextInput::make('icon')
                            ->label(__('Icon'))
                            ->default('✦')
                            ->maxLength(8)
                            ->helperText(__('A single character or emoji shown on the program card.')),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label(__('Cover Image'))
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Card Labels'))
                    ->description(__('Free text shown as small chips on the program card.'))
                    ->schema([
                        TranslatableInput::make('duration', __('Duration'), required: false),
                        TranslatableInput::make('price', __('Price'), required: false),
                        TranslatableInput::make('branches_label', __('Availability'), required: false),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make(__('Publishing'))
                    ->schema([
                        Select::make('subject_id')
                            ->label(__('Linked Course'))
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('Optional link to the internal course this program maps to.')),
                        TextInput::make('registration_url')
                            ->label(__('External Registration URL'))
                            ->url()
                            ->maxLength(255)
                            ->helperText(__('Leave empty to send visitors to the built-in join form.')),
                        TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label(__('Featured'))
                            ->inline(false),
                        Toggle::make('is_active')
                            ->label(__('Show on site'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label(__('Cover Image'))
                    ->collection('cover'),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('Category'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Program::categoryOptions()[$state] ?? $state),
                TextColumn::make('subject.name')
                    ->label(__('Linked Course'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('join_applications_count')
                    ->counts('joinApplications')
                    ->label(__('Join Requests')),
                IconColumn::make('is_featured')
                    ->label(__('Featured'))
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label(__('Show on site')),
                TextColumn::make('sort_order')
                    ->label(__('Sort Order'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('Category'))
                    ->options(Program::categoryOptions()),
                TernaryFilter::make('is_active')->label(__('Show on site')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(static::canUpdateRecords()),
                    DeleteAction::make()
                        ->visible(static::canDeleteRecords())
                        ->before(fn (Program $record) => static::guardDeletion($record)),
                    ForceDeleteAction::make()
                        ->visible(static::canDeleteRecords())
                        ->before(fn (Program $record) => static::guardDeletion($record)),
                    RestoreAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(static::canDeleteRecords())
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    ForceDeleteBulkAction::make()
                        ->visible(static::canDeleteRecords())
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    RestoreBulkAction::make()
                        ->visible(static::canDeleteRecords()),
                ]),
            ])
            ->reorderable('sort_order', fn (): bool => static::allows('update'))
            ->defaultSort('sort_order');
    }

    protected static function guardDeletion(Program $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'joinApplications' => __('Join Requests'),
        ]);
    }

    /**
     * @param  Collection<int, Program>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'joinApplications' => __('Join Requests'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePrograms::route('/'),
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
