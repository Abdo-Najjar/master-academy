<?php

namespace App\Filament\Admin\Resources\RoomBookings\Schemas;

use App\Filament\Support\BranchField;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\RoomAvailabilityService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RoomBookingForm
{
    /** @return array<string, string> */
    public static function dayOptions(): array
    {
        return [
            'saturday' => __('Saturday'),
            'sunday' => __('Sunday'),
            'monday' => __('Monday'),
            'tuesday' => __('Tuesday'),
            'wednesday' => __('Wednesday'),
            'thursday' => __('Thursday'),
            'friday' => __('Friday'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Booking Title'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('What the room is booked for — a workshop, an exam sitting, an outside event.')),
                        // Branch first, then the halls that stand in it: a
                        // booking is for a room at one site, and offering the
                        // whole centre's rooms invites filing it at the wrong
                        // one.
                        BranchField::make()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('room_id', null)),
                        Select::make('room_id')
                            ->label(__('Room'))
                            ->options(fn (Get $get): array => Room::query()
                                ->when($get('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                                ->get()
                                ->mapWithKeys(fn (Room $room): array => [$room->id => __('Room').' '.$room->number])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live(),
                        Select::make('status')
                            ->label(__('Booking Status'))
                            ->options(fn (): array => RoomBooking::statusOptions())
                            ->default(RoomBooking::STATUS_CONFIRMED)
                            ->native(false)
                            ->required()
                            ->helperText(__('A cancelled booking frees the room and stops counting as income.')),
                    ])
                    ->columns(2),

                Section::make('')
                    ->schema([
                        TextInput::make('client_name')
                            ->label(__('Booked By'))
                            ->maxLength(255),
                        TextInput::make('client_phone')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->maxLength(255),
                        DatePicker::make('start_date')
                            ->label(__('From Date'))
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->live(),
                        DatePicker::make('end_date')
                            ->label(__('To Date'))
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->afterOrEqual('start_date')
                            ->live(),
                    ])
                    ->columns(2),

                Section::make(__('Pricing'))
                    ->schema([
                        TextInput::make('price')
                            ->label(__('Booking Price'))
                            ->numeric()
                            ->prefix('₪')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->helperText(__('The agreed total. Money actually handed over is recorded as instalments against the booking.')),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('')
                    ->schema([
                        Repeater::make('times')
                            ->label(__('Booking Times'))
                            ->relationship('times')
                            ->schema([
                                Select::make('day')
                                    ->label(__('Day'))
                                    ->options(self::dayOptions())
                                    ->native(false)
                                    ->required(),
                                TimePicker::make('start_time')
                                    ->label(__('Start Time'))
                                    ->seconds(false)
                                    ->required(),
                                TimePicker::make('end_time')
                                    ->label(__('End Time'))
                                    ->seconds(false)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->defaultItems(1)
                            ->addActionLabel(__('Add Time'))
                            ->helperText(__('The hours the room is held on each day of the range. A one-day booking is a single row.'))
                            ->rules([
                                fn (Get $get, ?RoomBooking $record) => static::conflictRule($get, $record),
                            ]),
                    ]),
            ]);
    }

    /**
     * The three ways a set of booking slots can be wrong: it collides with
     * itself, it names a weekday the booking never runs on, or the room is
     * already held then — by a lesson or by another booking.
     */
    protected static function conflictRule(Get $get, ?RoomBooking $record): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
            $rows = array_values(array_filter(
                is_array($value) ? $value : [],
                fn ($row): bool => ! empty($row['day']) && ! empty($row['start_time']) && ! empty($row['end_time']),
            ));

            $roomId = $get('room_id');
            $from = $get('start_date');
            $to = $get('end_date');
            $cancelled = $get('status') === RoomBooking::STATUS_CANCELLED;
            $allowedDays = RoomAvailabilityService::weekdaysInRange($from, $to);

            foreach ($rows as $index => $row) {
                $day = strtolower((string) $row['day']);

                if ($row['start_time'] >= $row['end_time']) {
                    $fail(__('The end time must be after the start time.'));

                    return;
                }

                // "Tuesday 10–12" on a booking that runs Saturday to Sunday
                // holds a room for a day the booking never sees.
                if ($allowedDays !== [] && ! in_array($day, $allowedDays, true)) {
                    $fail(__(':day is outside the booking dates.', ['day' => __(ucfirst($day))]));

                    return;
                }

                foreach (array_slice($rows, $index + 1) as $other) {
                    if (strtolower((string) $other['day']) !== $day) {
                        continue;
                    }

                    if (RoomAvailabilityService::slotsOverlap(
                        $row['start_time'], $row['end_time'],
                        $other['start_time'], $other['end_time'],
                    )) {
                        $fail(__('This booking already has a slot on :day at :time', [
                            'day' => __(ucfirst($day)),
                            'time' => substr((string) $other['start_time'], 0, 5).' - '.substr((string) $other['end_time'], 0, 5),
                        ]));

                        return;
                    }
                }

                if (! $roomId || $cancelled) {
                    continue;
                }

                $occupant = RoomAvailabilityService::occupant(
                    roomId: (int) $roomId,
                    day: $day,
                    startTime: (string) $row['start_time'],
                    endTime: (string) $row['end_time'],
                    from: $from,
                    to: $to,
                    ignoreBookingId: $record?->id,
                );

                if ($occupant) {
                    $fail(RoomAvailabilityService::message($occupant, $day));

                    return;
                }
            }
        };
    }
}
