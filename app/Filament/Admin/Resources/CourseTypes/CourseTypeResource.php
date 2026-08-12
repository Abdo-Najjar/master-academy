<?php

namespace App\Filament\Admin\Resources\CourseTypes;

use App\Filament\Admin\Resources\CourseTypes\Pages\ManageCourseTypes;
use App\Filament\Admin\Resources\CourseTypes\Pages\ViewCourseType;
use App\Filament\Admin\Resources\CourseTypes\RelationManagers\SubjectsRelationManager;
use App\Filament\Admin\Resources\CourseTypes\Schemas\CourseTypeInfolist;
use App\Filament\Support\AuthorizesResourceActions;
use App\Filament\Support\DeletionGuard;
use App\Filament\Support\TranslatableInput;
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
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CourseTypeResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = CourseType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 1;

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

    public static function permissionPrefix(): string
    {
        return 'course_type';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TranslatableInput::make('name', __('Name')),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subjects_count')->counts('subjects')->label(__('Courses Count')),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
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

    public static function getRelations(): array
    {
        return [
            SubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCourseTypes::route('/'),
            'view' => ViewCourseType::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
