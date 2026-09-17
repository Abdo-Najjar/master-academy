<?php

namespace App\Filament\Admin\Resources\Registrations\Schemas;

use App\Filament\Support\AuditReasonField;
use App\Filament\Support\EnrollmentPayment;
use App\Filament\Support\SectionEnrolmentRules;
use App\Models\ExemptionType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('student_id')
                            ->label(__('Student'))
                            ->relationship('student', 'name')
                            ->searchable(['name', 'student_number', 'username', 'email', 'phone_number'])
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
                            ->relationship('section', 'name', modifyQueryUsing: fn ($query) => $query->whereNotNull('trainer_id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $section = Section::find($state);
                                    if ($section) {
                                        // Per-session sections are billed one
                                        // cycle at a time, so the first charge
                                        // is the cycle fee, not a course price.
                                        $amount = $section->displayFee();
                                        $set('amount_due', $amount);
                                        $set('amount_paid', $amount);
                                    }
                                }
                            })
                            ->rules([
                                fn () => SectionEnrolmentRules::hasTrainer(),
                                fn (callable $get, ?Registration $record) => SectionEnrolmentRules::noScheduleClash(
                                    $get('student_id') ? (int) $get('student_id') : null,
                                    $record?->id,
                                ),
                                fn (?Registration $record) => SectionEnrolmentRules::hasRoom($record?->id),
                            ]),
                        DatePicker::make('enrolled_at')
                            ->label(__('Section Enrollment Date'))
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->helperText(__('The day the student actually joined this section. Lessons held before it are not counted or charged — set it back when entering older registrations.')),
                        DatePicker::make('left_at')
                            ->label(__('Withdrawal Date'))
                            ->native(false)
                            ->visibleOn('edit')
                            ->afterOrEqual('enrolled_at')
                            ->live()
                            ->helperText(__('The first day the student is no longer in the section. Lessons held from this day on are not counted, not charged, and the student is off the attendance sheet. Set it back to record someone who stopped coming a while ago.')),
                        TextInput::make('leave_reason')
                            ->label(__('Withdrawal Reason'))
                            ->maxLength(255)
                            ->hiddenOn('create')
                            ->visible(fn (Get $get): bool => filled($get('left_at'))),
                    ])
                    ->columns(1),

                FormSection::make('')
                    ->schema([
                        Select::make('exemption_type_id')
                            ->label(__('Exemption Type'))
                            ->options(fn () => ExemptionType::query()
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (ExemptionType $t) => [
                                    $t->id => $t->getTranslation('name', app()->getLocale(), false),
                                ]))
                            ->searchable()
                            ->preload()
                            ->placeholder(__('No exemption'))
                            ->live()
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $due = (float) ($get('amount_due') ?? 0);
                                if (! $state) {
                                    return;
                                }
                                $type = ExemptionType::find($state);
                                $discount = $type ? $type->computeDiscount($due) : 0.0;
                                if ($discount > 0) {
                                    $set('exemption_amount', $discount);
                                    $set('amount_paid', max(0, $due - $discount));
                                }
                            }),
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
                            // Clearing the box means "no discount", not "unknown"
                            // — the column is NOT NULL, so an empty field has to
                            // reach the database as a zero.
                            ->dehydrateStateUsing(fn ($state) => blank($state) ? 0 : $state)
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
                    ->columns(1),

                FormSection::make('')
                    ->schema([
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpanFull(),
                        AuditReasonField::make(),
                    ]),

                // Only on create: an existing registration's money is adjusted
                // through the wallet actions, not by re-taking a payment here.
                FormSection::make(__('Payment'))
                    ->description(__('Money handed over now. It is deposited to the wallet before the section is charged, so the student does not end up owing what they just paid.'))
                    ->icon('heroicon-o-banknotes')
                    ->schema(EnrollmentPayment::schema(
                        fn (Get $get): float => (float) ($get('amount_paid') ?? 0)
                    ))
                    ->visibleOn('create')
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
