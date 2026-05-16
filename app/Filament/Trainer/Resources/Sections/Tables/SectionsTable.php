<?php

namespace App\Filament\Trainer\Resources\Sections\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('subject.name')->label(__('Subject'))->searchable(),
                TextColumn::make('start_date')->label(__('Start'))->date()->sortable(),
                TextColumn::make('end_date')->label(__('End'))->date()->sortable(),
                TextColumn::make('registrations_count')->counts('registrations')->label(__('Enrolled')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
