<?php

namespace App\Filament\Admin\Resources\Assignments\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label(__('Title'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable()->sortable(),
                TextColumn::make('section.subject.name')->label(__('Subject'))->toggleable(),
                TextColumn::make('trainer.name')->label(__('Trainer'))->searchable()->toggleable(),
                TextColumn::make('due_date')->label(__('Due Date'))->dateTime()->sortable(),
                TextColumn::make('max_points')->label(__('Max Points'))->sortable(),
                TextColumn::make('submissions_count')->counts('submissions')->label(__('Submissions')),
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
            ->defaultSort('due_date', 'desc');
    }
}
