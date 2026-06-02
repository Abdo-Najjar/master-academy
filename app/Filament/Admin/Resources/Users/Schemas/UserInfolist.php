<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—'),
                        TextEntry::make('email_verified_at')
                            ->label(__('Email verified at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('phone_number')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('whatsapp_number')->label(__('WhatsApp'))->placeholder('—'),
                        TextEntry::make('ssn')->label(__('SSN'))->placeholder('—'),
                        TextEntry::make('roles.name')
                            ->label(__('Roles'))
                            ->badge()
                            ->separator(',')
                            ->placeholder('—'),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (User $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
