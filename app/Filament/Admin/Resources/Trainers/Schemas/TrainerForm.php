<?php

namespace App\Filament\Admin\Resources\Trainers\Schemas;

use App\Filament\Admin\Resources\Trainers\Pages\CreateTrainer;
use App\Models\Trainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('main')
                            ->label(__('Image'))
                            ->collection('main')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->columnSpanFull(),
                        \App\Filament\Support\TranslatableInput::make('name', __('Full Name')),
                        DatePicker::make('dob')
                            ->label(__('Date of Birth'))
                            ->native(false)
                            ->maxDate(now()),
                        TextInput::make('ssn')
                            ->label(__('National ID / SSN'))
                            ->unique(table: 'trainers', column: 'ssn', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->unique(table: 'trainers', column: 'username', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('trainer_number')
                            ->label(__('Trainer Number'))
                            ->unique(table: 'trainers', column: 'trainer_number', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->unique(table: 'trainers', column: 'email', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required(fn ($livewire) => $livewire instanceof CreateTrainer)
                            ->minLength(6)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        TextInput::make('phone_number')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                if (filled($state) && blank($get('whatsapp_number'))) {
                                    $set('whatsapp_number', $state);
                                }
                            }),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->tel()
                            ->maxLength(255),
                        Select::make('governorate_id')
                            ->label(__('Governorate'))
                            ->relationship('governorate', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label(__('City'))
                            ->relationship('city', 'name', fn ($query, callable $get) => $query->where('governorate_id', $get('governorate_id')))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => empty($get('governorate_id'))),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        Select::make('subjects')
                            ->label(__('Subjects'))
                            ->multiple()
                            ->relationship('subjects', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('default_rate')
                            ->label(__('Default Rate (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(40)
                            ->step(0.01),
                        Textarea::make('bio')
                            ->label(__('Bio'))
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->helperText(__('Disabled trainers cannot sign in and are logged out on next request.'))
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
