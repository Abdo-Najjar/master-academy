<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use App\Filament\Support\EnrollmentPayment;
use App\Filament\Support\TranslatableInput;
use App\Models\City;
use App\Models\ExemptionType;
use App\Models\Governorate;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class QuickEnroll extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected string $view = 'filament.admin.pages.quick-enroll';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Students');
    }

    public static function getNavigationLabel(): string
    {
        return __('Quick Enroll');
    }

    public function getTitle(): string
    {
        return __('Quick Enroll Student');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('quick-enroll.access') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'is_active' => true,
            'exemption_amount' => 0,
            'payment_amount' => 0,
            'payment_date' => now(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FormSection::make(__('Student Information'))
                    ->description(__('Personal and account details for the new student.'))
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        TranslatableInput::make('name', __('Full Name')),
                        TextInput::make('username')
                            ->label(__('Username'))
                            ->required()
                            ->unique(table: 'students', column: 'username', modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6)
                            ->same('password_confirmation'),
                        TextInput::make('password_confirmation')
                            ->label(__('Confirm Password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6)
                            ->dehydrated(false),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->unique(table: 'students', column: 'email', modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        TextInput::make('ssn')
                            ->label(__('National ID / SSN'))
                            ->unique(table: 'students', column: 'ssn', modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'))
                            ->maxLength(255),
                        DatePicker::make('dob')
                            ->label(__('Date of Birth'))
                            ->native(false)
                            ->maxDate(now()),
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
                            ->options(Governorate::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label(__('City'))
                            ->options(fn (callable $get) => City::query()
                                ->where('governorate_id', $get('governorate_id'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => empty($get('governorate_id'))),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                FormSection::make(__('Registration'))
                    ->description(__('Add one or more sections. Each section is charged separately to the student wallet.'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Repeater::make('registrations')
                            ->label(__('Sections'))
                            ->hiddenLabel()
                            ->schema([
                                Select::make('section_id')
                                    ->label(__('Section'))
                                    ->options(fn () => Section::query()
                                        ->whereNotNull('trainer_id')
                                        ->with('subject')
                                        ->orderByDesc('id')
                                        ->get()
                                        ->mapWithKeys(fn ($s) => [
                                            $s->id => $s->name
                                                .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : '')
                                                .' ('.number_format((float) $s->price, 2).' ₪)',
                                        ]))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $section = Section::find($state);
                                            if ($section) {
                                                $set('amount_due', $section->price);
                                                $set('amount_paid', $section->price);
                                            }
                                        }
                                    })
                                    ->columnSpanFull(),

                                TextInput::make('amount_due')
                                    ->label(__('Amount Due'))
                                    ->numeric()
                                    ->prefix('₪')
                                    ->required()
                                    ->minValue(0)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $due = (float) ($get('amount_due') ?? 0);
                                        $exempt = (float) ($get('exemption_amount') ?? 0);
                                        $set('amount_paid', max(0, $due - $exempt));
                                    }),

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

                                TextInput::make('exemption_amount')
                                    ->label(__('Exemption / Discount'))
                                    ->numeric()
                                    ->prefix('₪')
                                    ->default(0)
                                    ->minValue(0)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $due = (float) ($get('amount_due') ?? 0);
                                        $exempt = (float) ($get('exemption_amount') ?? 0);
                                        $set('amount_paid', max(0, $due - $exempt));
                                    }),

                                TextInput::make('amount_paid')
                                    ->label(__('Amount To Be Paid'))
                                    ->numeric()
                                    ->prefix('₪')
                                    ->required()
                                    ->minValue(0)
                                    ->helperText(__('Will be auto-deducted from the student wallet on save. Negative balance is allowed.')),

                                Textarea::make('note')
                                    ->label(__('Note'))
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => filled($state['section_id'] ?? null)
                                ? Section::find($state['section_id'])?->name
                                : null)
                            ->addActionLabel(__('Add Section'))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable(false)
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                FormSection::make(__('Payment'))
                    ->description(__('Money handed over now. It is deposited to the wallet before the sections are charged, so the student does not end up owing what they just paid.'))
                    ->icon('heroicon-o-banknotes')
                    ->schema(EnrollmentPayment::schema(fn (Get $get): float => self::totalToPay($get)))
                    ->columns(1)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /** Sum of what the picked sections will charge the wallet. */
    protected static function totalToPay(Get $get): float
    {
        return collect($get('registrations') ?? [])
            ->sum(fn ($row): float => (float) ($row['amount_paid'] ?? 0));
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $registrationRows = $data['registrations'] ?? [];

        if (empty($registrationRows)) {
            Notification::make()
                ->danger()
                ->title(__('Could not enroll student'))
                ->body(__('Add at least one section.'))
                ->persistent()
                ->send();

            return;
        }

        $student = null;
        $sectionNames = [];

        try {
            DB::transaction(function () use ($data, $registrationRows, &$student, &$sectionNames): void {
                $student = Student::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'email' => $data['email'] ?? null,
                    'ssn' => $data['ssn'] ?? null,
                    'dob' => $data['dob'] ?? null,
                    'phone_number' => $data['phone_number'] ?? null,
                    'whatsapp_number' => $data['whatsapp_number'] ?? null,
                    'governorate_id' => $data['governorate_id'] ?? null,
                    'city_id' => $data['city_id'] ?? null,
                    'is_active' => true,
                ]);

                // Deposit BEFORE the sections are charged. RegistrationObserver
                // reads the wallet balance as it stands *before* each charge to
                // decide how much of it is really funded (and therefore how much
                // of the trainer's share to credit) — depositing afterwards
                // would leave every registration looking unpaid.
                EnrollmentPayment::collect($data, $student->id);

                $sectionIds = array_column($registrationRows, 'section_id');
                if (count($sectionIds) !== count(array_unique($sectionIds))) {
                    throw new \RuntimeException(__('The same section was selected more than once.'));
                }

                $allTimes = SectionTime::query()->whereIn('section_id', $sectionIds)->with('section')->get()->groupBy('section_id');

                foreach ($registrationRows as $row) {
                    $section = Section::find($row['section_id']);
                    if (! $section) {
                        continue;
                    }

                    $sectionNames[] = $section->name;

                    if (! $section->trainer_id) {
                        throw new \RuntimeException(
                            __('Section :name has no trainer assigned. Assign a trainer to the section before registering students.', [
                                'name' => $section->name,
                            ])
                        );
                    }

                    // Capacity check
                    if ($section->capacity) {
                        $enrolled = Registration::query()->where('section_id', $section->id)->count();
                        if ($enrolled >= $section->capacity) {
                            throw new \RuntimeException(
                                __('Section :name is full (capacity :capacity).', [
                                    'name' => $section->name,
                                    'capacity' => $section->capacity,
                                ])
                            );
                        }
                    }

                    // Schedule conflict check: against the student's other (pre-existing) sections
                    // and against the other sections picked in this same submission.
                    $otherSectionIds = Registration::query()
                        ->where('student_id', $student->id)
                        ->pluck('section_id')
                        ->merge(array_diff($sectionIds, [$row['section_id']]))
                        ->unique();

                    if ($otherSectionIds->isNotEmpty()) {
                        $newTimes = $allTimes->get($section->id, collect());
                        $otherTimes = SectionTime::query()->whereIn('section_id', $otherSectionIds)->with('section')->get();

                        foreach ($newTimes as $new) {
                            foreach ($otherTimes as $other) {
                                if (strtolower((string) $new->day) !== strtolower((string) $other->day)) {
                                    continue;
                                }
                                if ($new->start_time < $other->end_time && $new->end_time > $other->start_time) {
                                    throw new \RuntimeException(
                                        __('Schedule conflict between :section and :other on :day at :time', [
                                            'section' => $section->name,
                                            'other' => $other->section?->name ?? '#'.$other->section_id,
                                            'day' => __(ucfirst((string) $new->day)),
                                            'time' => substr((string) $other->start_time, 0, 5).' - '.substr((string) $other->end_time, 0, 5),
                                        ])
                                    );
                                }
                            }
                        }
                    }

                    Registration::create([
                        'student_id' => $student->id,
                        'section_id' => $row['section_id'],
                        'amount_due' => $row['amount_due'],
                        'amount_paid' => $row['amount_paid'],
                        'exemption_amount' => $row['exemption_amount'] ?? 0,
                        'exemption_type_id' => $row['exemption_type_id'] ?? null,
                        'trainer_amount' => 0,
                        'note' => $row['note'] ?? null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('Could not enroll student'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        $body = __('Student :name has been created and registered in: :sections', [
            'name' => is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name,
            'sections' => implode(', ', $sectionNames),
        ]);

        if ((float) ($data['payment_amount'] ?? 0) > 0) {
            $body .= ' — '.__('Paid :amount, wallet balance is now :balance', [
                'amount' => number_format((float) $data['payment_amount'], 2).' ₪',
                'balance' => number_format((float) $student->fresh()->balanceFloat, 2).' ₪',
            ]);
        }

        Notification::make()
            ->success()
            ->title(__('Student enrolled successfully'))
            ->body($body)
            ->send();

        $this->redirect(StudentResource::getUrl('view', ['record' => $student->id]));
    }
}
