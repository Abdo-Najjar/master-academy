<?php

namespace App\Filament\Admin\Resources\Subjects;

use App\Filament\Admin\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Admin\Resources\Subjects\Pages\ViewSubject;
use App\Filament\Admin\Resources\Subjects\RelationManagers\SectionsRelationManager;
use App\Filament\Admin\Resources\Subjects\Schemas\SubjectForm;
use App\Filament\Admin\Resources\Subjects\Schemas\SubjectInfolist;
use App\Filament\Admin\Resources\Subjects\Tables\SubjectsTable;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\Subject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubjectResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Course');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Courses');
    }

    public static function permissionPrefix(): string
    {
        return 'subject';
    }

    public static function form(Schema $schema): Schema
    {
        return SubjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
            'view' => ViewSubject::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
