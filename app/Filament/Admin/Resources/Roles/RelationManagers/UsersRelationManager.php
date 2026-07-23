<?php

namespace App\Filament\Admin\Resources\Roles\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Users with this Role');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('Email'))->searchable()->sortable(),
                TextColumn::make('created_at')->label(__('Registered At'))->dateTime()->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->visible(fn () => auth()->user()?->can('role.update') ?? false),
            ])
            ->recordActions([
                DetachAction::make()
                    ->visible(fn () => auth()->user()?->can('role.update') ?? false),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->visible(fn () => auth()->user()?->can('role.update') ?? false),
            ])
            ->emptyStateHeading(__('No users assigned'))
            ->emptyStateDescription(__('No users have been assigned to this role yet.'));
    }
}
