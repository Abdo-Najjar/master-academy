<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Models\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;

class SectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfoSection::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Section Name'))->columnSpanFull(),
                        TextEntry::make('subject.name')
                            ->label(__('Course'))
                            ->badge()
                            ->color(fn ($record) => $record->subject?->color ? \Filament\Support\Colors\Color::hex($record->subject->color) : 'gray')
                            ->placeholder('—'),
                        TextEntry::make('trainer.name')->label(__('Trainer'))->placeholder('—'),
                        TextEntry::make('start_date')->label(__('Start Date'))->date()->placeholder('—'),
                        TextEntry::make('end_date')->label(__('End Date'))->date()->placeholder('—'),
                        TextEntry::make('price')
                            ->label(__('Price'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('trainer_rate')
                            ->label(__('Trainer Rate (%)'))
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).' %' : '—'),
                        TextEntry::make('capacity')->label(__('Capacity'))->numeric()->placeholder('—'),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Section $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
