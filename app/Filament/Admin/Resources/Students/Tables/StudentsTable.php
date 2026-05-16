<?php

namespace App\Filament\Admin\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('main')
                    ->label(__('Image'))
                    ->collection('main')
                    ->circular(),
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('student_number')->label(__('Student Number'))->searchable(),
                TextColumn::make('username')->label(__('Username'))->searchable(),
                TextColumn::make('ssn')->label(__('SSN'))->searchable()->toggleable(),
                TextColumn::make('phone_number')->label(__('Phone'))->searchable(),
                TextColumn::make('educationalLevel.name')->label(__('Level'))->sortable(),
                TextColumn::make('governorate.name')->label(__('Governorate'))->toggleable(),
                TextColumn::make('city.name')->label(__('City'))->toggleable(),
                TextColumn::make('registrations_count')->counts('registrations')->label(__('Registrations')),
                TextColumn::make('balanceFloat')->label(__('Wallet Balance'))->money('USD')->getStateUsing(fn ($record) => $record->balanceFloat),
                TextColumn::make('dob')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('educational_level_id')
                    ->label(__('Educational Level'))
                    ->relationship('educationalLevel', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('governorate_id')
                    ->label(__('Governorate'))
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
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
