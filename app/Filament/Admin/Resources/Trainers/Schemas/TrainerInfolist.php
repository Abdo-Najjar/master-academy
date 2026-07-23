<?php

namespace App\Filament\Admin\Resources\Trainers\Schemas;

use App\Models\Trainer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Name'))->columnSpanFull(),
                        TextEntry::make('trainer_number')->label(__('Trainer Number'))->placeholder('—'),
                        TextEntry::make('username')->label(__('Username'))->placeholder('—'),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—'),
                        TextEntry::make('ssn')->label(__('SSN'))->placeholder('—'),
                        TextEntry::make('dob')->label(__('Date of Birth'))->date()->placeholder('—'),
                        TextEntry::make('phone_number')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('whatsapp_number')->label(__('WhatsApp'))->placeholder('—'),
                        TextEntry::make('governorate.name')->label(__('Governorate'))->placeholder('—'),
                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                        TextEntry::make('default_rate')
                            ->label(__('Default Rate (%)'))
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2).' %' : '—'),
                        TextEntry::make('balanceFloat')
                            ->label(__('Wallet Balance'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪')
                            ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success'),
                        TextEntry::make('subjects_badges')
                            ->label(__('Courses'))
                            ->state(fn (Trainer $record): string => $record->subjects
                                ->map(function ($subject): string {
                                    $hex = ltrim((string) ($subject->color ?: '#6b7280'), '#');
                                    if (strlen($hex) === 3) {
                                        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                                    }
                                    $r = (int) hexdec(substr($hex, 0, 2));
                                    $g = (int) hexdec(substr($hex, 2, 2));
                                    $b = (int) hexdec(substr($hex, 4, 2));
                                    $text = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#111827' : '#ffffff';
                                    $name = e($subject->getTranslation('name', app()->getLocale(), false));

                                    return '<span style="display:inline-block;padding:2px 10px;margin:2px;border-radius:9999px;font-size:.75rem;font-weight:600;background:#'.$hex.';color:'.$text.';">'.$name.'</span>';
                                })
                                ->implode(' '))
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('bio')->label(__('Bio'))->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Trainer $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
