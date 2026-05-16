<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Models\Room;
use App\Models\Trainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Section Details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Section Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('subject_id')
                            ->label(__('Subject'))
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('trainer_id', null)),
                        Select::make('trainer_id')
                            ->label(__('Trainer'))
                            ->options(function (callable $get): array {
                                $subjectId = $get('subject_id');
                                if (! $subjectId) {
                                    return Trainer::query()->orderBy('name')->pluck('name', 'id')->all();
                                }
                                return Trainer::query()
                                    ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectId))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $trainer = Trainer::find($state);
                                    if ($trainer && (float) $trainer->default_rate > 0) {
                                        $set('trainer_rate', $trainer->default_rate);
                                    }
                                }
                            }),
                    ])
                    ->columns(2),

                Section::make(__('Schedule'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ])
                    ->columns(2),

                Section::make(__('Pricing & Capacity'))
                    ->schema([
                        TextInput::make('price')
                            ->label(__('Price'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$'),
                        TextInput::make('trainer_rate')
                            ->label(__('Trainer Rate (%)'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText(__('Leave empty to use trainer default rate')),
                        TextInput::make('capacity')
                            ->label(__('Capacity'))
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columns(3),

                Section::make(__('Online Access'))
                    ->schema([
                        TextInput::make('google_meet_url')->label(__('Google Meet URL'))->url(),
                        TextInput::make('google_classroom_url')->label(__('Google Classroom URL'))->url(),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make(__('Times'))
                    ->schema([
                        Repeater::make('times')
                            ->label(__('Lecture Times'))
                            ->relationship('times')
                            ->schema([
                                Select::make('day')
                                    ->label(__('Day'))
                                    ->options([
                                        'saturday' => __('Saturday'),
                                        'sunday' => __('Sunday'),
                                        'monday' => __('Monday'),
                                        'tuesday' => __('Tuesday'),
                                        'wednesday' => __('Wednesday'),
                                        'thursday' => __('Thursday'),
                                        'friday' => __('Friday'),
                                    ])
                                    ->required(),
                                TimePicker::make('start_time')
                                    ->label(__('Start Time'))
                                    ->seconds(false)
                                    ->required(),
                                TimePicker::make('end_time')
                                    ->label(__('End Time'))
                                    ->seconds(false)
                                    ->required(),
                                Select::make('room_id')
                                    ->label(__('Room'))
                                    ->options(Room::query()->orderBy('number')->pluck('number', 'id'))
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel(__('Add Time')),
                    ]),
            ]);
    }
}
