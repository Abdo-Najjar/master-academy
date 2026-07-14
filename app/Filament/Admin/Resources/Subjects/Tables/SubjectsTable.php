<?php

namespace App\Filament\Admin\Resources\Subjects\Tables;

use App\Filament\Support\DeletionGuard;
use App\Models\Subject;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->badge()
                    ->color(fn ($record) => $record->color ? \Filament\Support\Colors\Color::hex($record->color) : 'gray')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')->label(__('Color')),
                TextColumn::make('trainers_count')->counts('trainers')->label(__('Trainers')),
                TextColumn::make('sections_count')->counts('sections')->label(__('Sections')),
                TextColumn::make('sort_order')->label(__('Sort Order'))->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (Subject $record) => static::guardDeletion($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    ForceDeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    protected static function guardDeletion(Subject $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'sections' => __('Sections'),
        ]);
    }

    /**
     * @param  Collection<int, Subject>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'sections' => __('Sections'),
        ]);
    }
}
