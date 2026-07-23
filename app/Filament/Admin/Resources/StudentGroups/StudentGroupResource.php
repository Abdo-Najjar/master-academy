<?php

namespace App\Filament\Admin\Resources\StudentGroups;

use App\Filament\Admin\Resources\StudentGroups\Pages\ManageStudentGroups;
use App\Filament\Admin\Resources\StudentGroups\Schemas\StudentGroupForm;
use App\Filament\Admin\Resources\StudentGroups\Tables\StudentGroupsTable;
use App\Models\StudentGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentGroupResource extends Resource
{
    protected static ?string $model = StudentGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }

    public static function getModelLabel(): string
    {
        return __('Student Group');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Student Groups');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('student_group.index') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return StudentGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStudentGroups::route('/'),
        ];
    }
}
