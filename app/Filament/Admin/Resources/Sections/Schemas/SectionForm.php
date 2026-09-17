<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Filament\Support\AuditReasonField;
use App\Filament\Support\BranchField;
use App\Filament\Support\TrainerRateField;
use App\Models\Room;
use App\Models\Section as SectionModel;
use App\Models\SectionTime;
use App\Models\Trainer;
use App\Services\RoomAvailabilityService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                        // Required now that branches are walls rather than
                        // labels: a course with no branch belongs to no site,
                        // and every employee tied to one would lose sight of it.
                        BranchField::make()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('times', [])),
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
                        // A section has a floor as well as a ceiling: below the
                        // minimum it is not worth running, above the maximum
                        // there is no seat left.
                        TextInput::make('min_capacity')
                            ->label(__('Minimum Capacity'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('The number of students the section needs to run. Leave empty for no minimum.'))
                            ->lte('capacity'),
                        TextInput::make('capacity')
                            ->label(__('Maximum Capacity'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('Seats available. Enrolment is blocked once they are all taken. Leave empty for no limit.'))
                            ->gte('min_capacity'),
                        TextInput::make('training_hours')
                            ->label(__('Training Hours'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(1),

                Section::make(__('Pricing'))
                    ->schema([
                        Select::make('fee_type')
                            ->label(__('Fee Type'))
                            ->options(SectionModel::feeTypeOptions())
                            ->default(SectionModel::FEE_TYPE_FIXED_COURSE)
                            ->required()
                            ->live()
                            ->helperText(__('Per-session billing charges the cycle fee every N sessions held, regardless of attendance.')),
                        TextInput::make('price')
                            ->label(__('Course Fee'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('₪')
                            ->visible(fn (Get $get): bool => $get('fee_type') !== SectionModel::FEE_TYPE_PER_SESSIONS),
                        TextInput::make('sessions_per_cycle')
                            ->label(__('Sessions Per Payment Cycle'))
                            ->numeric()
                            ->minValue(1)
                            ->default(8)
                            ->required(fn (Get $get): bool => $get('fee_type') === SectionModel::FEE_TYPE_PER_SESSIONS)
                            ->visible(fn (Get $get): bool => $get('fee_type') === SectionModel::FEE_TYPE_PER_SESSIONS),
                        TextInput::make('cycle_fee')
                            ->label(__('Fee Per Cycle'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('₪')
                            ->required(fn (Get $get): bool => $get('fee_type') === SectionModel::FEE_TYPE_PER_SESSIONS)
                            ->visible(fn (Get $get): bool => $get('fee_type') === SectionModel::FEE_TYPE_PER_SESSIONS),
                        Toggle::make('auto_charge_cycles')
                            ->label(__('Charge the cycle automatically'))
                            ->default(true)
                            ->helperText(__('The cycle fee is taken from the student wallet as soon as the cycle\'s last session is recorded. Turn this off to only flag the student as due and collect by hand.'))
                            ->visible(fn (Get $get): bool => $get('fee_type') === SectionModel::FEE_TYPE_PER_SESSIONS),
                        ...TrainerRateField::make(
                            'trainer_rate',
                            helperText: __('Leave empty to use trainer default rate'),
                            default: 40,
                        ),
                        AuditReasonField::make(),
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
                                // Only rooms that stand in this course's own
                                // branch: booking a hall at another site is not
                                // a mistake anyone should be able to make.
                                Select::make('room_id')
                                    ->label(__('Room'))
                                    ->options(fn (Get $get): array => Room::query()
                                        ->when($get('../../branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                                        ->orderBy('number')
                                        ->pluck('number', 'id')
                                        ->all())
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

                                    // A course that is over holds neither its
                                    // trainer nor its room, and two courses that
                                    // never run together never collide — so only
                                    // sections overlapping this one's dates count.
                                    $running = fn ($q) => $q->runningBetween($get('start_date'), $get('end_date'));

                                    // A section cannot overlap itself: the same
                                    // students would have to sit in two lessons
                                    // at once, whatever rooms they are in. The
                                    // rows are compared against each other, not
                                    // against the database — they are still only
                                    // form state here, and swapping two slots
                                    // passes through a moment where the saved
                                    // rows do hold both times.
                                    $entered = array_values(array_filter(
                                        $rows,
                                        fn ($row): bool => ! empty($row['day']) && ! empty($row['start_time']) && ! empty($row['end_time']),
                                    ));

                                    foreach ($entered as $i => $row) {
                                        foreach (array_slice($entered, $i + 1) as $other) {
                                            if (strtolower((string) $row['day']) !== strtolower((string) $other['day'])) {
                                                continue;
                                            }

                                            if (RoomAvailabilityService::slotsOverlap(
                                                $row['start_time'], $row['end_time'],
                                                $other['start_time'], $other['end_time'],
                                            )) {
                                                $fail(__('This section already has a lesson on :day at :time', [
                                                    'day' => __(ucfirst((string) $row['day'])),
                                                    'time' => substr((string) $other['start_time'], 0, 5).' - '.substr((string) $other['end_time'], 0, 5),
                                                ]));

                                                return;
                                            }
                                        }
                                    }

                                    foreach ($rows as $row) {
                                        if (empty($row['day']) || empty($row['start_time']) || empty($row['end_time'])) {
                                            continue;
                                        }

                                        if ($trainerId) {
                                            $conflict = RoomAvailabilityService::applyOverlap(
                                                SectionTime::query()->where('day', $row['day']),
                                                'section_times',
                                                (string) $row['start_time'],
                                                (string) $row['end_time'],
                                            )
                                                ->whereHas('section', fn ($q) => $running($q)
                                                    ->where('trainer_id', $trainerId)
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

                                        // Rooms are held by halls let out to
                                        // outsiders as well as by lessons, and
                                        // this has to say so *before* the save
                                        // — the observer would only refuse the
                                        // time rows, leaving the section behind.
                                        if (! empty($row['room_id'])) {
                                            $occupant = RoomAvailabilityService::occupant(
                                                roomId: (int) $row['room_id'],
                                                day: (string) $row['day'],
                                                startTime: (string) $row['start_time'],
                                                endTime: (string) $row['end_time'],
                                                from: $get('start_date'),
                                                to: $get('end_date'),
                                                ignoreSectionId: $record?->id,
                                            );

                                            if ($occupant) {
                                                $fail(RoomAvailabilityService::message($occupant, (string) $row['day']));

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
