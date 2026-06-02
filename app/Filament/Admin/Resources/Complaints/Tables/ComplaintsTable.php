<?php

namespace App\Filament\Admin\Resources\Complaints\Tables;

use App\Models\Complaint;
use App\Models\Student;
use App\Models\Trainer;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComplaintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('complainable_type')
                    ->label(__('From'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => class_basename($state) === 'Student' ? __('Student') : __('Trainer'))
                    ->color(fn (string $state): string => class_basename($state) === 'Student' ? 'info' : 'success'),
                TextColumn::make('complainable.name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Complaint::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Complaint::STATUS_OPEN => 'warning',
                        Complaint::STATUS_IN_PROGRESS => 'info',
                        Complaint::STATUS_RESOLVED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('handler.name')
                    ->label(__('Handled By'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Submitted'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(Complaint::statuses()),
                SelectFilter::make('complainable_type')
                    ->label(__('From'))
                    ->options([
                        Student::class => __('Student'),
                        Trainer::class => __('Trainer'),
                    ]),
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
            ->defaultSort('id', 'desc');
    }
}
