<?php

namespace App\Filament\Admin\Resources\Registrations\Schemas;

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('student_id')
                            ->label(__('Student'))
                            ->relationship('student', 'name')
                            ->searchable(['student_number', 'username', 'email', 'phone_number'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $student = Student::find($state);
                                    if ($student) {
                                        $set('student_balance_display', number_format((float) $student->balanceFloat, 2));
                                    }
                                }
                            }),
                        TextInput::make('student_balance_display')
                            ->label(__('Student Wallet Balance'))
                            ->disabled()
                            ->dehydrated(false)
                            ->prefix('₪'),
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->relationship('section', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $section = Section::find($state);
                                    if ($section) {
                                        $set('amount_due', $section->price);
                                        $set('amount_paid', $section->price);
                                    }
                                }
                            })
                            ->rules([
                                fn (callable $get, ?Registration $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    $studentId = $get('student_id');
                                    if (! $studentId || ! $value) {
                                        return;
                                    }

                                    $otherSectionIds = Registration::query()
                                        ->where('student_id', $studentId)
                                        ->where('section_id', '!=', $value)
                                        ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->pluck('section_id');

                                    if ($otherSectionIds->isEmpty()) {
                                        return;
                                    }

                                    $newTimes = SectionTime::query()->where('section_id', $value)->get();
                                    $otherTimes = SectionTime::query()->whereIn('section_id', $otherSectionIds)->with('section')->get();

                                    foreach ($newTimes as $new) {
                                        foreach ($otherTimes as $other) {
                                            if (strtolower((string) $new->day) !== strtolower((string) $other->day)) {
                                                continue;
                                            }
                                            if ($new->start_time < $other->end_time && $new->end_time > $other->start_time) {
                                                $sectionName = $other->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$other->section_id;
                                                $fail(__('Schedule conflict with the student\'s other section :name on :day at :time', [
                                                    'name' => $sectionName,
                                                    'day' => __(ucfirst((string) $new->day)),
                                                    'time' => substr((string) $other->start_time, 0, 5).' - '.substr((string) $other->end_time, 0, 5),
                                                ]));
                                                return;
                                            }
                                        }
                                    }
                                },
                                fn (?Registration $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                                    if (! $value) {
                                        return;
                                    }
                                    $section = Section::find($value);
                                    if (! $section || ! $section->capacity) {
                                        return;
                                    }
                                    $enrolled = Registration::query()
                                        ->where('section_id', $value)
                                        ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->count();
                                    if ($enrolled >= $section->capacity) {
                                        $fail(__('This section is full (capacity :capacity).', ['capacity' => $section->capacity]));
                                    }
                                },
                            ]),
                        Select::make('payment_type_id')
                            ->label(__('Payment Type'))
                            ->relationship('paymentType', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                FormSection::make('')
                    ->schema([
                        TextInput::make('amount_due')
                            ->label(__('Amount Due'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('₪')
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $due = (float) ($get('amount_due') ?? 0);
                                $exemption = (float) ($get('exemption_amount') ?? 0);
                                $set('amount_paid', max(0, $due - $exemption));
                            }),
                        TextInput::make('exemption_amount')
                            ->label(__('Exemption / Discount'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('₪')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $due = (float) ($get('amount_due') ?? 0);
                                $exemption = (float) ($get('exemption_amount') ?? 0);
                                $set('amount_paid', max(0, $due - $exemption));
                            }),
                        TextInput::make('amount_paid')
                            ->label(__('Amount To Be Paid'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('₪')
                            ->required()
                            ->helperText(__('Will be auto-deducted from the student wallet on save.')),
                    ])
                    ->columns(3),

                FormSection::make('')
                    ->schema([
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
