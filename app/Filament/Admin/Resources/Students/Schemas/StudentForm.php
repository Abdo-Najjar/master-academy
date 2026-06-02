<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Filament\Admin\Resources\Students\Pages\CreateStudent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
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
                            ->unique(table: 'students', column: 'ssn', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('')
                    ->schema([
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->unique(table: 'students', column: 'username', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('student_number')
                            ->label(__('Student Number'))
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(__('Auto-generated'))
                            ->visibleOn('edit'),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->unique(table: 'students', column: 'email', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required(fn ($livewire) => $livewire instanceof CreateStudent)
                            ->minLength(6)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null),
                    ])
                    ->columns(2),

                Section::make('')
                    ->schema([
                        TextInput::make('phone_number')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('parent_name')
                            ->label(__('Parent Name'))
                            ->maxLength(255),
                        TextInput::make('parent_phone')
                            ->label(__('Parent Phone'))
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('parent_whatsapp')
                            ->label(__('Parent WhatsApp'))
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
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->helperText(__('Disabled students cannot sign in and are logged out on next request.'))
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
