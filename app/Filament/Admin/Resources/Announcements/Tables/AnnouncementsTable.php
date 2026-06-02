<?php

namespace App\Filament\Admin\Resources\Announcements\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label(__('Title'))->searchable()->limit(50),
                IconColumn::make('all_sections')
                    ->label(__('All Sections'))
                    ->boolean(),
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label(__('Sections')),
                TextColumn::make('published_at')->label(__('Published'))->dateTime()->sortable(),
                TextColumn::make('expires_at')->label(__('Expires'))->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('creator.name')->label(__('Created By'))->toggleable(),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
