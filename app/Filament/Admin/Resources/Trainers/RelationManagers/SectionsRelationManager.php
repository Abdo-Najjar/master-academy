<?php

namespace App\Filament\Admin\Resources\Trainers\RelationManagers;

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

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return SectionsTable::configure($table)->recordTitleAttribute('name');
    }
}
