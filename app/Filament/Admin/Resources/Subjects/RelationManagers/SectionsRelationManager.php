<?php

namespace App\Filament\Admin\Resources\Subjects\RelationManagers;

use App\Filament\Admin\Resources\Sections\Schemas\SectionForm;
use App\Filament\Admin\Resources\Sections\Tables\SectionsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Sections');
    }

    protected static string $relationship = 'sections';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return SectionForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return SectionsTable::configure($table)->recordTitleAttribute('name')->emptyStateHeading(__('No records found'));
    }
}
