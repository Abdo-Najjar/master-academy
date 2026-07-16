<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\RelationManagers;

use App\Models\WhatsappCampaignRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Recipients');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Student'))->searchable(),
                TextColumn::make('phone')->label(__('Phone'))->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WhatsappCampaignRecipient::STATUS_SENT => 'success',
                        WhatsappCampaignRecipient::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        WhatsappCampaignRecipient::STATUS_SENT => __('Sent'),
                        WhatsappCampaignRecipient::STATUS_FAILED => __('Failed'),
                        default => __('Pending'),
                    }),
                TextColumn::make('sent_at')->label(__('Sent At'))->dateTime()->placeholder('—'),
                TextColumn::make('error')->label(__('Error'))->limit(40)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        WhatsappCampaignRecipient::STATUS_PENDING => __('Pending'),
                        WhatsappCampaignRecipient::STATUS_SENT => __('Sent'),
                        WhatsappCampaignRecipient::STATUS_FAILED => __('Failed'),
                    ]),
            ])
            ->poll('10s')
            ->defaultSort('id')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
