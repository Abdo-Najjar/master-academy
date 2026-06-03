<?php

namespace App\Filament\Admin\Resources\Exams\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradesRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Grades');
    }

    protected static string $relationship = 'grades';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('score')
                ->label(__('Score'))
                ->numeric()
                ->required(),
            TextInput::make('note')
                ->label(__('Note'))
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->emptyStateHeading(__('No records found'))
            ->columns([
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('student.student_number')->label(__('Student Number')),
                TextColumn::make('score')->label(__('Score'))->sortable(),
                TextColumn::make('note')->label(__('Note'))->limit(40),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->toggleable(),
            ])
            ->defaultSort('score', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
