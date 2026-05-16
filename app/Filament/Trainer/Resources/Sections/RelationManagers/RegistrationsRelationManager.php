<?php

namespace App\Filament\Trainer\Resources\Sections\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Enrolled Students';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('student.student_number')->label(__('Student Number'))->searchable(),
                TextColumn::make('student.phone_number')->label(__('Phone')),
                TextColumn::make('amount_paid')->label(__('Paid'))->money('USD'),
                TextColumn::make('created_at')->label(__('Enrolled At'))->dateTime(),
            ])
            ->defaultSort('id', 'desc');
    }
}
