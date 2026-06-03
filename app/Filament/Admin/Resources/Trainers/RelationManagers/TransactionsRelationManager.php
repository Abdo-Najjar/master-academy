<?php

namespace App\Filament\Admin\Resources\Trainers\RelationManagers;

use App\Models\Trainer;
use Bavix\Wallet\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsRelationManager extends RelationManager
{
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Transactions');
    }

    protected static string $relationship = 'transactions';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $owner = $this->getOwnerRecord();
                if ($owner instanceof Trainer && $owner->wallet) {
                    return Transaction::query()->where('wallet_id', $owner->wallet->id);
                }

                return $query->whereRaw('1 = 0');
            })
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'deposit' ? __('Deposit') : __('Withdrawal'))
                    ->color(fn (string $state): string => $state === 'deposit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn ($record): string => number_format($record->amount / 100, 2).' ₪')
                    ->sortable(),
                Tables\Columns\TextColumn::make('meta.description')
                    ->label(__('Description'))
                    ->placeholder(__('N/A')),
                Tables\Columns\TextColumn::make('meta.note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->placeholder(__('N/A')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
