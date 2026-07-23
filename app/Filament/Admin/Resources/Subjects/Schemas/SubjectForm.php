<?php

namespace App\Filament\Admin\Resources\Subjects\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        \App\Filament\Support\TranslatableInput::make('name', __('Name')),
                        Select::make('course_type_id')
                            ->label(__('Course Type'))
                            ->relationship('courseType', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('trainers')
                            ->label(__('Trainers'))
                            ->multiple()
                            ->relationship('trainers', 'name')
                            ->searchable()
                            ->preload(),
                        ColorPicker::make('color')
                            ->label(__('Color')),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
