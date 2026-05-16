<?php

namespace App\Filament\Trainer\Resources\Sections\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Section Info'))
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->disabled(),
                        TextInput::make('subject.name')->label(__('Subject'))->disabled(),
                        TextInput::make('google_meet_url')->label(__('Google Meet URL'))->url(),
                        TextInput::make('google_classroom_url')->label(__('Google Classroom URL'))->url(),
                    ])
                    ->columns(2),

                Section::make(__('Materials'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('materials')
                            ->collection('materials')
                            ->multiple()
                            ->reorderable()
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
