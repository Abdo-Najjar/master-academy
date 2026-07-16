<?php

namespace App\Filament\Admin\Resources\Assignments\Schemas;

use App\Models\Assignment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('title')->label(__('Title'))->columnSpanFull(),
                        TextEntry::make('section.name')->label(__('Section'))->placeholder('—'),
                        TextEntry::make('section.subject.name')->label(__('Subject'))->placeholder('—'),
                        TextEntry::make('trainer.name')->label(__('Trainer'))->placeholder('—'),
                        TextEntry::make('due_date')->label(__('Due Date'))->dateTime()->placeholder('—'),
                        TextEntry::make('max_points')->label(__('Max Points'))->placeholder('—'),
                        TextEntry::make('submissions_count')
                            ->label(__('Submissions'))
                            ->state(fn (Assignment $record): int => $record->submissions()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('description')->label(__('Description'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Assignment $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
