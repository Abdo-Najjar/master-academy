<?php

namespace App\Filament\Admin\Resources\Exams\Schemas;

use App\Models\Exam;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExamInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Exam Name'))->columnSpanFull(),
                        TextEntry::make('section.name')->label(__('Section'))->placeholder('—'),
                        TextEntry::make('section.subject.name')->label(__('Subject'))->placeholder('—'),
                        TextEntry::make('section.trainer.name')->label(__('Trainer'))->placeholder('—'),
                        TextEntry::make('date')->label(__('Date'))->date()->placeholder('—'),
                        TextEntry::make('max_score')->label(__('Max Score'))->placeholder('—'),
                        TextEntry::make('grades_count')
                            ->label(__('Graded'))
                            ->state(fn (Exam $record): int => $record->grades()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('note')->label(__('Note'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Exam $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
