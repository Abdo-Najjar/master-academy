<?php

namespace App\Filament\Admin\Resources\Students\RelationManagers;

use App\Filament\Admin\Resources\Registrations\Schemas\RegistrationForm;
use App\Filament\Admin\Resources\Registrations\Tables\RegistrationsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Registrations');
    }

    protected static string $relationship = 'registrations';

    public function form(Schema $schema): Schema
    {
        return RegistrationForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RegistrationsTable::configure($table)->recordTitleAttribute('id');
    }
}
