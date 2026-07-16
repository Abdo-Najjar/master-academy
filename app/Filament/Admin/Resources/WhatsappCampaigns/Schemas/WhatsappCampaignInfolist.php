<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Schemas;

use App\Models\WhatsappCampaign;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                        TextEntry::make('studentGroup.name')->label(__('Student Group'))->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (WhatsappCampaign $record): string => match ($record->status) {
                                WhatsappCampaign::STATUS_RUNNING => 'warning',
                                WhatsappCampaign::STATUS_COMPLETED => 'success',
                                WhatsappCampaign::STATUS_CANCELLED => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                WhatsappCampaign::STATUS_RUNNING => __('Running'),
                                WhatsappCampaign::STATUS_COMPLETED => __('Completed'),
                                WhatsappCampaign::STATUS_CANCELLED => __('Cancelled'),
                                default => __('Draft'),
                            }),
                        TextEntry::make('total_count')->label(__('Total Recipients')),
                        TextEntry::make('sent_count')->label(__('Sent'))->badge()->color('success'),
                        TextEntry::make('failed_count')->label(__('Failed'))->badge()->color('danger'),
                        TextEntry::make('started_at')->label(__('Started At'))->dateTime()->placeholder('—'),
                        TextEntry::make('completed_at')->label(__('Completed At'))->dateTime()->placeholder('—'),
                        TextEntry::make('message')->label(__('Message'))->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
