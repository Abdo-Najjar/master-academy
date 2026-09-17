<?php

namespace App\Filament\Admin\Resources\Students\RelationManagers;

use App\Models\Student;
use App\Support\ReceiptAttachment;
use Bavix\Wallet\Models\Transaction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransactionsRelationManager extends RelationManager
{
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Transactions');
    }

    protected static string $relationship = 'transactions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class);
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
                if ($owner instanceof Student && $owner->wallet) {
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
                // Both carry the whole "who paid for what, in which section"
                // line, which is far wider than the column: truncate, and hand
                // the full text over on hover.
                Tables\Columns\TextColumn::make('meta.description')
                    ->label(__('Description'))
                    ->limit(50)
                    ->tooltip(fn (Transaction $record): ?string => $record->meta['description'] ?? null)
                    ->wrap()
                    ->placeholder(__('N/A')),
                Tables\Columns\TextColumn::make('meta.note')
                    ->label(__('Note'))
                    ->limit(50)
                    ->tooltip(fn (Transaction $record): ?string => $record->meta['note'] ?? null)
                    ->wrap()
                    ->placeholder(__('N/A')),
                Tables\Columns\TextColumn::make('receipt')
                    ->label(__('Receipt'))
                    ->state(fn (Transaction $record): ?string => ReceiptAttachment::walletUrl($record->meta) ? __('View') : null)
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Transaction $record): ?string => ReceiptAttachment::walletUrl($record->meta), shouldOpenInNewTab: true)
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
