<?php

namespace App\Filament\Admin\Resources\StudentGroups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                        TextEntry::make('students.name')
                            ->label(__('Students'))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('contacts')
                            ->label(__('Additional Contacts'))
                            ->state(fn ($record) => $record->contacts->map(fn ($c) => "{$c->name} — {$c->phone}")->all())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
