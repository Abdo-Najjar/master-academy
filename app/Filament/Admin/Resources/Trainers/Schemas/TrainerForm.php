<?php

namespace App\Filament\Admin\Resources\Trainers\Schemas;

use App\Filament\Admin\Resources\Trainers\Pages\CreateTrainer;
use App\Models\Trainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Personal Information'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('main')
                            ->label(__('Image'))
                            ->collection('main')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->label(__('Full Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        DatePicker::make('dob')
                            ->label(__('Date of Birth'))
                            ->native(false)
                            ->maxDate(now()),
                        TextInput::make('ssn')
                            ->label(__('National ID / SSN'))
                            ->unique(table: 'trainers', column: 'ssn', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make(__('Account'))
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
                    ->columns(2),

                Section::make(__('Contact'))
                    ->schema([
                        TextInput::make('phone_number')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->maxLength(255),
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
                            ->visible(fn (callable $get) => filled($get('governorate_id'))),
                    ])
                    ->columns(2),

                Section::make(__('Training Details'))
                    ->schema([
                        Select::make('subjects')
                            ->label(__('Subjects'))
                            ->multiple()
                            ->relationship('subjects', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('default_rate')
                            ->label(__('Default Rate (%)'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->step(0.01),
                        Textarea::make('bio')
                            ->label(__('Bio'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
