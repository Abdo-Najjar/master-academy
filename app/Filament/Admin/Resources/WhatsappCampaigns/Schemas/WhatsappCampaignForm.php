<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappCampaignForm
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
                        Select::make('student_group_id')
                            ->label(__('Student Group'))
                            ->relationship('studentGroup', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('message')
                            ->label(__('Message'))
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
