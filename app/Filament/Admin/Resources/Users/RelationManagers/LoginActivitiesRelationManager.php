<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginActivitiesRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Login Activities');
    }

    protected static string $relationship = 'loginActivities';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('logged_in_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ip')->label(__('IP'))->searchable(),
                TextColumn::make('browser')->label(__('Browser')),
                TextColumn::make('platform')->label(__('Platform'))->toggleable(),
                TextColumn::make('device')->label(__('Device'))->toggleable(),
                TextColumn::make('guard')->label(__('Guard'))->toggleable(),
            ])
            ->defaultSort('logged_in_at', 'desc')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
