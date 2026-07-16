<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Tables;

use App\Models\WhatsappCampaign;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('studentGroup.name')->label(__('Student Group')),
                TextColumn::make('status')
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
                TextColumn::make('total_count')->label(__('Total')),
                TextColumn::make('sent_count')->label(__('Sent'))->badge()->color('success'),
                TextColumn::make('failed_count')->label(__('Failed'))->badge()->color('danger'),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        WhatsappCampaign::STATUS_DRAFT => __('Draft'),
                        WhatsappCampaign::STATUS_RUNNING => __('Running'),
                        WhatsappCampaign::STATUS_COMPLETED => __('Completed'),
                        WhatsappCampaign::STATUS_CANCELLED => __('Cancelled'),
                    ]),
            ])
            ->recordActions([
                Action::make('launch')
                    ->label(__('Launch'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (WhatsappCampaign $record): bool => $record->status === WhatsappCampaign::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->modalDescription(fn (WhatsappCampaign $record): string => __('This will send the message to :count recipient(s), 40-60 seconds apart. It cannot be undone.', ['count' => $record->recipients()->count() ?: $record->studentGroup?->students()->count() ?? 0]))
                    ->action(function (WhatsappCampaign $record): void {
                        $count = \App\Services\WhatsappCampaignService::buildRecipients($record);

                        if ($count === 0) {
                            Notification::make()->warning()->title(__('No recipients with a valid WhatsApp number in this group'))->send();
                            return;
                        }

                        // Flip to "running" here rather than waiting for the spawned
                        // background process to boot and do it — that boot can take a
                        // few seconds, during which the table would still show the
                        // "Launch" button as if nothing happened.
                        $record->update(['status' => WhatsappCampaign::STATUS_RUNNING, 'started_at' => now()]);

                        \App\Services\WhatsappCampaignService::launch($record);

                        Notification::make()->success()->title(__('Campaign launched'))->body(__(':count message(s) queued for sending.', ['count' => $count]))->send();
                    }),
                Action::make('cancel')
                    ->label(__('Cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WhatsappCampaign $record): bool => $record->status === WhatsappCampaign::STATUS_RUNNING)
                    ->requiresConfirmation()
                    ->action(function (WhatsappCampaign $record): void {
                        $record->update(['status' => WhatsappCampaign::STATUS_CANCELLED]);
                        Notification::make()->warning()->title(__('Campaign will stop after the current message'))->send();
                    }),
                ActionGroup::make([
                    ViewAction::make(),
                    DeleteAction::make()
                        ->visible(fn (WhatsappCampaign $record): bool => $record->status !== WhatsappCampaign::STATUS_RUNNING),
                ]),
            ])
            ->poll('5s')
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('No records found'));
    }
}
