<?php

namespace App\Filament\Admin\Resources\RoomBookings\Schemas;

use App\Models\RoomBooking;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;

class RoomBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (float $amount): string => number_format($amount, 2).' ₪';

        return $schema
            ->components([
                InfoSection::make('')
                    ->schema([
                        TextEntry::make('title')->label(__('Booking Title'))->columnSpanFull(),
                        TextEntry::make('room.number')
                            ->label(__('Room'))
                            ->formatStateUsing(fn (?string $state): string => $state ? __('Room').' '.$state : '—')
                            ->placeholder('—'),
                        TextEntry::make('branch.name')->label(__('Branch'))->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Booking Status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => RoomBooking::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                RoomBooking::STATUS_CONFIRMED => 'success',
                                RoomBooking::STATUS_TENTATIVE => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('client_name')->label(__('Booked By'))->placeholder('—'),
                        TextEntry::make('client_phone')->label(__('Phone Number'))->placeholder('—'),
                        TextEntry::make('start_date')->label(__('From Date'))->date(),
                        TextEntry::make('end_date')->label(__('To Date'))->date(),
                        TextEntry::make('times')
                            ->label(__('Booking Times'))
                            ->state(fn (RoomBooking $record): string => $record->timesLabel() ?: '—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                InfoSection::make(__('Financial Status'))
                    ->schema([
                        TextEntry::make('price')
                            ->label(__('Booking Price'))
                            ->state(fn (RoomBooking $record): string => $money((float) $record->price)),
                        TextEntry::make('paid')
                            ->label(__('Paid'))
                            ->state(fn (RoomBooking $record): string => $money($record->paidAmount())),
                        TextEntry::make('remaining')
                            ->label(__('Remaining'))
                            ->state(fn (RoomBooking $record): string => $money($record->remainingAmount()))
                            ->badge()
                            ->color(fn (RoomBooking $record): string => $record->remainingAmount() > 0.009 ? 'danger' : 'success'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                InfoSection::make('')
                    ->schema([
                        TextEntry::make('note')->label(__('Note'))->placeholder('—')->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
