<?php

namespace App\Filament\Admin\Resources\Subjects\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Select::make('educational_level_id')
                    ->label(__('Educational Level'))
                    ->relationship('educationalLevel', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('trainers')
                    ->label(__('Trainers'))
                    ->multiple()
                    ->relationship('trainers', 'name')
                    ->searchable()
                    ->preload(),
                ColorPicker::make('color')
                    ->label(__('Color')),
                TextInput::make('sort_order')
                    ->label(__('Sort Order'))
                    ->numeric()
                    ->default(0),
            ]);
    }
}
