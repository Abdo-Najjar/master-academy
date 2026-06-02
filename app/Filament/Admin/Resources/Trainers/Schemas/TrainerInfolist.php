<?php

namespace App\Filament\Admin\Resources\Trainers\Schemas;

use App\Models\Trainer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                        TextEntry::make('trainer_number')->label(__('Trainer Number'))->placeholder('—'),
                        TextEntry::make('username')->label(__('Username'))->placeholder('—'),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—'),
                        TextEntry::make('ssn')->label(__('SSN'))->placeholder('—'),
                        TextEntry::make('dob')->label(__('Date of Birth'))->date()->placeholder('—'),
                        TextEntry::make('phone_number')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('whatsapp_number')->label(__('WhatsApp'))->placeholder('—'),
                        TextEntry::make('governorate.name')->label(__('Governorate'))->placeholder('—'),
                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                        TextEntry::make('default_rate')
                            ->label(__('Default Rate (%)'))
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).' %' : '—'),
                        TextEntry::make('balanceFloat')
                            ->label(__('Wallet Balance'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪')
                            ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success'),
                        TextEntry::make('bio')->label(__('Bio'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Trainer $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
