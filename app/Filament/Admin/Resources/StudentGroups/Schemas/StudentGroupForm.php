<?php

namespace App\Filament\Admin\Resources\StudentGroups\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        CheckboxList::make('students')
                            ->label(__('Students'))
                            ->relationship('students', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(1)
                            ->gridDirection('row')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
