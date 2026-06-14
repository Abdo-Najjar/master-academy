<?php

namespace App\Filament\Admin\Resources\Parents\Schemas;

use App\Filament\Admin\Resources\Parents\Pages\CreateParent;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class ParentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Full Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->required()
                            ->unique(table: 'parents', column: 'phone', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('whatsapp')
                            ->label(__('WhatsApp Number'))
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required(fn ($livewire) => $livewire instanceof CreateParent)
                            ->minLength(6)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
