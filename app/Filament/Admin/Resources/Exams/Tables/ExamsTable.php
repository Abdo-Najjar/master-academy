<?php

namespace App\Filament\Admin\Resources\Exams\Tables;

use App\Filament\Admin\Resources\Exams\Actions\TogglePublishGradesAction;
use App\Models\Exam;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Exam Name'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->sortable(),
                TextColumn::make('section.subject.name')->label(__('Course'))->toggleable(),
                TextColumn::make('section.trainer.name')->label(__('Trainer'))->searchable()->toggleable(),
                TextColumn::make('date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('max_score')->label(__('Max Score'))->sortable(),
                TextColumn::make('grades_count')->counts('grades')->label(__('Graded')),
                IconColumn::make('grades_published_at')
                    ->label(__('Published'))
                    ->boolean()
                    ->state(fn (Exam $record): bool => $record->isGradesPublished()),
            ])
            ->filters([
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                TogglePublishGradesAction::make(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
