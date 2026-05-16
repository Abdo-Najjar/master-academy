<?php

namespace App\Filament\Admin\Resources\Trainers\Schemas;

use App\Models\Trainer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TrainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->columnSpanFull(),
                TextEntry::make('dob')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('ssn')
                    ->placeholder('-'),
                TextEntry::make('username')
                    ->placeholder('-'),
                TextEntry::make('trainer_number')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone_number')
                    ->placeholder('-'),
                TextEntry::make('whatsapp_number')
                    ->placeholder('-'),
                TextEntry::make('governorate.name')
                    ->label('Governorate')
                    ->placeholder('-'),
                TextEntry::make('city.name')
                    ->label('City')
                    ->placeholder('-'),
                TextEntry::make('default_rate')
                    ->numeric(),
                TextEntry::make('bio')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Trainer $record): bool => $record->trashed()),
            ]);
    }
}
