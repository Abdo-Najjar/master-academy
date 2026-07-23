<?php

namespace App\Filament\Admin\Resources\CourseTypes;

use App\Filament\Admin\Resources\CourseTypes\Pages\ManageCourseTypes;
use App\Filament\Support\DeletionGuard;
use App\Models\CourseType;
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
use Filament\Forms\Components\ColorPicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CourseTypeResource extends Resource
{
    protected static ?string $model = CourseType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Course Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Course Types');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('course_type.index') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        \App\Filament\Support\TranslatableInput::make('name', __('Name')),
                        ColorPicker::make('color')
                            ->label(__('Color')),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->badge()
                    ->color(fn ($record) => $record->color ? \Filament\Support\Colors\Color::hex($record->color) : 'gray')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')->label(__('Color')),
                TextColumn::make('subjects_count')->counts('subjects')->label(__('Courses Count')),
                TextColumn::make('sort_order')->label(__('Sort Order'))->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (CourseType $record) => static::guardDeletion($record)),
                    ForceDeleteAction::make()
                        ->before(fn (CourseType $record) => static::guardDeletion($record)),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    ForceDeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    protected static function guardDeletion(CourseType $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'subjects' => __('Courses'),
        ]);
    }

    /**
     * @param  Collection<int, CourseType>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'subjects' => __('Courses'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCourseTypes::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
