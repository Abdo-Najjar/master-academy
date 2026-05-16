<?php

namespace App\Filament\Student\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('student.name')
                    ->label('Student'),
                TextEntry::make('section.name')
                    ->label('Section'),
                TextEntry::make('paymentType.name')
                    ->label('Payment type')
                    ->placeholder('-'),
                TextEntry::make('amount_due')
                    ->numeric(),
                TextEntry::make('amount_paid')
                    ->numeric(),
                TextEntry::make('exemption_amount')
                    ->numeric(),
                TextEntry::make('trainer_amount')
                    ->numeric(),
                TextEntry::make('note')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Registration $record): bool => $record->trashed()),
            ]);
    }
}
