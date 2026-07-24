<?php

namespace App\Filament\Admin\Resources\CourseTypes\Schemas;

use App\Models\CourseType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->badge()
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (CourseType $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
