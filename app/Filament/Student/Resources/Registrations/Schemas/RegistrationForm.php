<?php

namespace App\Filament\Student\Resources\Registrations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->relationship('student', 'name')
                    ->required(),
                Select::make('section_id')
                    ->relationship('section', 'name')
                    ->required(),
                Select::make('payment_type_id')
                    ->relationship('paymentType', 'name'),
                TextInput::make('amount_due')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('amount_paid')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('exemption_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('trainer_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('note'),
            ]);
    }
}
