<?php

namespace App\Filament\Admin\Resources\Subjects\Schemas;

use App\Models\Subject;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Subject'))
                            ->badge()
                            ->color(fn (Subject $record) => $record->color ? \Filament\Support\Colors\Color::hex($record->color) : 'gray')
                            ->columnSpanFull(),
                        ColorEntry::make('color')->label(__('Color'))->placeholder('—'),
                        TextEntry::make('sort_order')->label(__('Sort Order'))->numeric(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Subject $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
