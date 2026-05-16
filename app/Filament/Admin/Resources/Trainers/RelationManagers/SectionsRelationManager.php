<?php

namespace App\Filament\Admin\Resources\Trainers\RelationManagers;

use App\Filament\Admin\Resources\Sections\Tables\SectionsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sections';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return SectionsTable::configure($table)->recordTitleAttribute('name');
    }
}
