<?php

namespace App\Filament\Admin\Resources\Parents\Schemas;

use App\Models\ParentGuardian;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Full Name')),
                        TextEntry::make('phone')->label(__('Phone Number')),
                        TextEntry::make('whatsapp')->label(__('WhatsApp'))->placeholder('—'),
                        IconEntry::make('is_active')->label(__('Active'))->boolean(),
                        TextEntry::make('students_count')
                            ->label(__('Linked Students'))
                            ->state(fn (ParentGuardian $record): int => $record->students()->count()),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (ParentGuardian $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
