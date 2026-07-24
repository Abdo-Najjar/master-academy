<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Models\Room;
use App\Models\Section as SectionModel;
use App\Models\SectionTime;
use App\Models\Trainer;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Section Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('subject_id')
                            ->label(__('Course'))
                            ->relationship('subject', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('trainer_id', null)),
                        Select::make('trainer_id')
                            ->label(__('Trainer'))
                            ->options(function (Get $get): array {
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
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $trainer = Trainer::find($state);
                                    if ($trainer && (float) $trainer->default_rate > 0) {
                                        $set('trainer_rate', $trainer->default_rate);
                                    }
                                }
                            }),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        Select::make('section_type')
                            ->label(__('Section Type'))
                            ->options([
                                'male' => __('Male'),
                                'female' => __('Female'),
                                'mixed' => __('Mixed'),
                            ])
                            ->default('mixed')
                            ->required(),
                        TextInput::make('capacity')
                            ->label(__('Capacity'))
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('training_hours')
                            ->label(__('Training Hours'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(1),

                Section::make(__('Pricing'))
                    ->schema([
                        TextInput::make('price')
                            ->label(__('Course Fee'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('₪'),
                        TextInput::make('trainer_rate')
                            ->label(__('Trainer Rate (%)'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(40)
                            ->suffix('%')
                            ->helperText(__('Leave empty to use trainer default rate')),
                        Select::make('seat_reservation_type')
                            ->label(__('Seat Reservation Type'))
                            ->options([
                                'fixed' => __('Fixed Amount'),
                                'percentage' => __('Percentage of Price'),
                            ])
                            ->live(),
                        TextInput::make('seat_reservation_amount')
                            ->label(__('Seat Reservation Amount'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->visible(fn (callable $get) => filled($get('seat_reservation_type')))
                            ->prefix(fn (callable $get) => $get('seat_reservation_type') === 'percentage' ? '%' : '₪'),
                    ])
                    ->columns(1),

                Section::make('')
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
                            ->addActionLabel(__('Add Time'))
                            ->rules([
                                fn (Get $get, ?SectionModel $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    $rows = is_array($value) ? $value : [];
                                    $trainerId = $get('trainer_id');

                                    foreach ($rows as $row) {
                                        if (empty($row['day']) || empty($row['start_time']) || empty($row['end_time'])) {
                                            continue;
                                        }

                                        if ($trainerId) {
                                            $conflict = SectionTime::query()
                                                ->where('day', $row['day'])
                                                ->where('start_time', '<', $row['end_time'])
                                                ->where('end_time', '>', $row['start_time'])
                                                ->whereHas('section', fn ($q) => $q->where('trainer_id', $trainerId)
                                                    ->when($record?->id, fn ($q2) => $q2->where('id', '!=', $record->id)))
                                                ->with('section')
                                                ->first();

                                            if ($conflict) {
                                                $fail(__('Trainer is already teaching :name on :day at :time', [
                                                    'name' => $conflict->section?->name ?? '#'.$conflict->section_id,
                                                    'day' => __(ucfirst((string) $row['day'])),
                                                    'time' => substr((string) $conflict->start_time, 0, 5).' - '.substr((string) $conflict->end_time, 0, 5),
                                                ]));

                                                return;
                                            }
                                        }

                                        if (! empty($row['room_id'])) {
                                            $conflict = SectionTime::query()
                                                ->where('day', $row['day'])
                                                ->where('room_id', $row['room_id'])
                                                ->where('start_time', '<', $row['end_time'])
                                                ->where('end_time', '>', $row['start_time'])
                                                ->when($record?->id, fn ($q) => $q->where('section_id', '!=', $record->id))
                                                ->with('section')
                                                ->first();

                                            if ($conflict) {
                                                $fail(__('Room is already used by :name on :day at :time', [
                                                    'name' => $conflict->section?->name ?? '#'.$conflict->section_id,
                                                    'day' => __(ucfirst((string) $row['day'])),
                                                    'time' => substr((string) $conflict->start_time, 0, 5).' - '.substr((string) $conflict->end_time, 0, 5),
                                                ]));

                                                return;
                                            }
                                        }
                                    }
                                },
                            ]),
                    ]),
            ]);
    }
}
