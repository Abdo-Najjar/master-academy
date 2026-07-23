<?php

namespace App\Filament\Admin\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('student.name')->label(__('Student')),
                        TextEntry::make('section.name')->label(__('Section')),
                        TextEntry::make('paymentType.name')->label(__('Payment Type'))->placeholder('—'),
                        TextEntry::make('amount_due')
                            ->label(__('Amount Due'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('amount_paid')
                            ->label(__('Amount Paid'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('exemptionType.name')
                            ->label(__('Exemption Type'))
                            ->placeholder('—'),
                        TextEntry::make('exemption_amount')
                            ->label(__('Exemption / Discount'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('trainer_amount')
                            ->label(__('Trainer Share'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('trainer_credited_amount')
                            ->label(__('Trainer Share Credited'))
                            ->badge()
                            ->color(fn (Registration $record): string => (float) $record->trainer_credited_amount >= (float) $record->trainer_amount ? 'success' : 'warning')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪'),
                        TextEntry::make('note')->label(__('Note'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Registration $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
