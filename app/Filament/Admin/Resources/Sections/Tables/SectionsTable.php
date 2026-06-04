<?php

namespace App\Filament\Admin\Resources\Sections\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('Subject'))
                    ->badge()
                    ->color(fn ($record) => $record->subject?->color ? \Filament\Support\Colors\Color::hex($record->subject->color) : 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trainer.name')->label(__('Trainer'))->searchable()->sortable(),
                TextColumn::make('start_date')->label(__('Start'))->date()->sortable(),
                TextColumn::make('end_date')->label(__('End'))->date()->sortable(),
                TextColumn::make('price')->label(__('Price'))->money('ILS', decimalPlaces: 0)->sortable(),
                TextColumn::make('trainer_rate')->label(__('Trainer Rate'))->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').' %')->sortable(),
                TextColumn::make('capacity')->label(__('Capacity'))->sortable(),
                TextColumn::make('registrations_count')->counts('registrations')->label(__('Enrolled')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __(ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('subject_id')
                    ->label(__('Subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('trainer_id')
                    ->label(__('Trainer'))
                    ->relationship('trainer', 'name')
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
