<?php

namespace App\Filament\Admin\Resources\Sections\RelationManagers;

use App\Filament\Admin\Resources\Registrations\Schemas\RegistrationForm;
use App\Filament\Admin\Resources\Registrations\Tables\RegistrationsTable;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegistrationsRelationManager extends RelationManager
{
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Registrations');
    }

    protected static string $relationship = 'registrations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return RegistrationForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Numbered, because this is a section's roster: the students in it run
        // 1 to 30, whatever their registration ids happen to be. The heading
        // carries the count, which is the other thing the page never said.
        return RegistrationsTable::configure($table, numbered: true)
            ->recordTitleAttribute('id')
            ->heading(fn (): string => __('Registrations').' — '.$this->getOwnerRecord()->seatsSummary())
            ->emptyStateHeading(__('No records found'));
    }
}
