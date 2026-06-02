<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Models\Student;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->columnSpanFull(),
                        TextEntry::make('student_number')->label(__('Student Number'))->placeholder('—'),
                        TextEntry::make('username')->label(__('Username'))->placeholder('—'),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—'),
                        TextEntry::make('ssn')->label(__('SSN'))->placeholder('—'),
                        TextEntry::make('dob')->label(__('Date of Birth'))->date()->placeholder('—'),
                        TextEntry::make('phone_number')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('whatsapp_number')->label(__('WhatsApp'))->placeholder('—'),
                        TextEntry::make('parent_name')->label(__('Parent Name'))->placeholder('—'),
                        TextEntry::make('parent_phone')->label(__('Parent Phone'))->placeholder('—'),
                        TextEntry::make('parent_whatsapp')->label(__('Parent WhatsApp'))->placeholder('—'),
                        TextEntry::make('governorate.name')->label(__('Governorate'))->placeholder('—'),
                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                        TextEntry::make('balanceFloat')
                            ->label(__('Wallet Balance'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪')
                            ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success'),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Student $record): bool => $record->trashed()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
