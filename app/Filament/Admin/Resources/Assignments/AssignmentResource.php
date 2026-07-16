<?php

namespace App\Filament\Admin\Resources\Assignments;

use App\Filament\Admin\Resources\Assignments\Pages\CreateAssignment;
use App\Filament\Admin\Resources\Assignments\Pages\EditAssignment;
use App\Filament\Admin\Resources\Assignments\Pages\ListAssignments;
use App\Filament\Admin\Resources\Assignments\Pages\ViewAssignment;
use App\Filament\Admin\Resources\Assignments\RelationManagers\SubmissionsRelationManager;
use App\Filament\Admin\Resources\Assignments\Schemas\AssignmentForm;
use App\Filament\Admin\Resources\Assignments\Schemas\AssignmentInfolist;
use App\Filament\Admin\Resources\Assignments\Tables\AssignmentsTable;
use App\Models\Assignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;

class AssignmentResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Assignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Assignment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Assignments');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('assignment.index');
    }

    public function defineGates(): array
    {
        return [
            'assignment.index' => __('View'),
            'assignment.create' => __('Create'),
            'assignment.update' => __('Update'),
            'assignment.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return AssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignments::route('/'),
            'create' => CreateAssignment::route('/create'),
            'view' => ViewAssignment::route('/{record}'),
            'edit' => EditAssignment::route('/{record}/edit'),
        ];
    }
}
