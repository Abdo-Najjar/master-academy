<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Models\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->columnSpanFull(),
                TextEntry::make('subject.name')
                    ->label('Subject'),
                TextEntry::make('trainer.name')
                    ->label('Trainer')
                    ->placeholder('-'),
                TextEntry::make('start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('end_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('trainer_rate')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('capacity')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('google_meet_url')
                    ->placeholder('-'),
                TextEntry::make('google_classroom_url')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Section $record): bool => $record->trashed()),
            ]);
    }
}
