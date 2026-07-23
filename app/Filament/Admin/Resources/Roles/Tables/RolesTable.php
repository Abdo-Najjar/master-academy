<?php

namespace App\Filament\Admin\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('guard_name', 'web'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('Role Name')),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label(__('Permissions')),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->sortable()
                    ->label(__('Users')),
                TextColumn::make('created_at')
                    ->sortable()
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => auth()->user()?->can('role.update') ?? false),
                DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('role.delete') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('role.delete') ?? false),
                ]),
            ]);
    }
}
