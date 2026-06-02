<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Settings\AppSettings;
use BackedEnum;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;

class QuickEnroll extends Page implements HasForms
{
    use HasHexaLite;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected string $view = 'filament.admin.pages.quick-enroll';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
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
        return hexa()->can('student.create') && hexa()->can('registration.create');
    }

    public function mount(): void
    {
        $this->form->fill([
            'is_active' => true,
            'exemption_amount' => 0,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make(__('Student Information'))
                    ->description(__('Personal and account details for the new student.'))
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Full Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
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
                            ->minLength(6),
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
                        TextInput::make('parent_name')
                            ->label(__('Parent Name'))
                            ->maxLength(255),
                        TextInput::make('parent_phone')
                            ->label(__('Parent Phone'))
                            ->tel()
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                $settings = app(AppSettings::class);
                                $pct = (int) $settings->sibling_discount_percent;
                                if ($pct <= 0) {
                                    return;
                                }
                                $hasSibling = Student::query()->where('parent_phone', $state)->exists();
                                if (! $hasSibling) {
                                    return;
                                }
                                $due = (float) ($get('amount_due') ?? 0);
                                if ($due <= 0) {
                                    return;
                                }
                                $discount = round($due * $pct / 100, 2);
                                $set('exemption_amount', $discount);
                                $set('amount_paid', max(0, $due - $discount));
                                Notification::make()
                                    ->success()
                                    ->title(__('Sibling discount applied'))
                                    ->body(__(':percent% discount auto-applied (:amount)', ['percent' => $pct, 'amount' => number_format($discount, 2).' ₪']))
                                    ->send();
                            }),
                        TextInput::make('parent_whatsapp')
                            ->label(__('Parent WhatsApp'))
                            ->tel()
                            ->maxLength(255),
                        Select::make('governorate_id')
                            ->label(__('Governorate'))
                            ->relationship('governorate', 'name')
                            ->options(\App\Models\Governorate::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label(__('City'))
                            ->options(fn (callable $get) => \App\Models\City::query()
                                ->where('governorate_id', $get('governorate_id'))
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (callable $get) => empty($get('governorate_id'))),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                FormSection::make(__('Registration'))
                    ->description(__('Pick the section and confirm the amounts. The amount paid will be charged to the student wallet automatically.'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(fn () => Section::query()
                                ->with('subject')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => $s->getTranslation('name', app()->getLocale(), false)
                                        .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : '')
                                        .' ('.number_format((float) $s->price, 2).' ₪)',
                                ]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $section = Section::find($state);
                                    if ($section) {
                                        $set('amount_due', $section->price);
                                        $set('amount_paid', $section->price);
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('payment_type_id')
                            ->label(__('Payment Type'))
                            ->options(PaymentType::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        TextInput::make('amount_due')
                            ->label(__('Amount Due'))
                            ->numeric()
                            ->prefix('₪')
                            ->required()
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $due = (float) ($get('amount_due') ?? 0);
                                $exempt = (float) ($get('exemption_amount') ?? 0);
                                $set('amount_paid', max(0, $due - $exempt));
                            }),

                        TextInput::make('exemption_amount')
                            ->label(__('Exemption / Discount'))
                            ->numeric()
                            ->prefix('₪')
                            ->default(0)
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (callable $get, callable $set) {
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
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $student = null;
        $registration = null;

        try {
            DB::transaction(function () use ($data, &$student, &$registration): void {
                $student = Student::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'email' => $data['email'] ?? null,
                    'ssn' => $data['ssn'] ?? null,
                    'dob' => $data['dob'] ?? null,
                    'phone_number' => $data['phone_number'] ?? null,
                    'whatsapp_number' => $data['whatsapp_number'] ?? null,
                    'parent_name' => $data['parent_name'] ?? null,
                    'parent_phone' => $data['parent_phone'] ?? null,
                    'parent_whatsapp' => $data['parent_whatsapp'] ?? null,
                    'governorate_id' => $data['governorate_id'] ?? null,
                    'city_id' => $data['city_id'] ?? null,
                    'is_active' => true,
                ]);

                // Capacity + schedule conflict checks
                $section = Section::find($data['section_id']);
                if ($section) {
                    if ($section->capacity) {
                        $enrolled = Registration::query()->where('section_id', $section->id)->count();
                        if ($enrolled >= $section->capacity) {
                            throw new \RuntimeException(
                                __('This section is full (capacity :capacity).', ['capacity' => $section->capacity])
                            );
                        }
                    }

                    $otherSectionIds = Registration::query()
                        ->where('student_id', $student->id)
                        ->pluck('section_id');

                    if ($otherSectionIds->isNotEmpty()) {
                        $newTimes = SectionTime::query()->where('section_id', $section->id)->get();
                        $otherTimes = SectionTime::query()->whereIn('section_id', $otherSectionIds)->with('section')->get();

                        foreach ($newTimes as $new) {
                            foreach ($otherTimes as $other) {
                                if (strtolower((string) $new->day) !== strtolower((string) $other->day)) {
                                    continue;
                                }
                                if ($new->start_time < $other->end_time && $new->end_time > $other->start_time) {
                                    throw new \RuntimeException(
                                        __('Schedule conflict with the student\'s other section :name on :day at :time', [
                                            'name' => $other->section?->getTranslation('name', app()->getLocale(), false) ?? '#'.$other->section_id,
                                            'day' => __(ucfirst((string) $new->day)),
                                            'time' => substr((string) $other->start_time, 0, 5).' - '.substr((string) $other->end_time, 0, 5),
                                        ])
                                    );
                                }
                            }
                        }
                    }
                }

                $registration = Registration::create([
                    'student_id' => $student->id,
                    'section_id' => $data['section_id'],
                    'payment_type_id' => $data['payment_type_id'] ?? null,
                    'amount_due' => $data['amount_due'],
                    'amount_paid' => $data['amount_paid'],
                    'exemption_amount' => $data['exemption_amount'] ?? 0,
                    'trainer_amount' => 0,
                    'note' => $data['note'] ?? null,
                ]);
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

        Notification::make()
            ->success()
            ->title(__('Student enrolled successfully'))
            ->body(__('Student :name has been created and registered in :section', [
                'name' => is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name,
                'section' => Section::find($data['section_id'])?->getTranslation('name', app()->getLocale(), false) ?? '#'.$data['section_id'],
            ]))
            ->send();

        $this->redirect(StudentResource::getUrl('view', ['record' => $student->id]));
    }
}
