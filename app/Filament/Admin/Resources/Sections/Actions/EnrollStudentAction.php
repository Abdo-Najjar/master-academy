<?php

namespace App\Filament\Admin\Resources\Sections\Actions;

use App\Filament\Support\EnrollmentPayment;
use App\Filament\Support\SectionEnrolmentRules;
use App\Models\ExemptionType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Put a student the centre already has into the section being looked at.
 *
 * Enrolment used to run one way only — open the student, pick a course — which
 * is the wrong way round for the job the desk actually does at the start of a
 * term: it has a section open and a queue of people to put in it. This is the
 * same enrolment, entered from the other end, with the section fixed to the
 * page and the student the thing being chosen.
 *
 * Same fee, same exemption handling and the same "take the money now" block as
 * every other enrolment screen, so nothing can be slipped past by using this
 * door instead.
 */
class EnrollStudentAction
{
    public static function make(): Action
    {
        return Action::make('enrollExistingStudent')
            ->label(__('Enroll an Existing Student'))
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('registration.create') ?? false)
            ->modalHeading(fn (Section $record): string => __('Enroll a Student in :name', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('Enroll'))
            ->modalWidth('2xl')
            ->fillForm(fn (Section $record): array => [
                'enrolled_at' => now(),
                'amount_due' => $record->displayFee(),
                'amount_paid' => $record->displayFee(),
                'exemption_amount' => 0,
                'payment_amount' => 0,
                'payment_date' => now(),
            ])
            ->schema(fn (Section $record): array => self::schema($record))
            ->action(fn (Section $record, array $data) => self::enroll($record, $data));
    }

    /** @return list<mixed> */
    protected static function schema(Section $record): array
    {
        return [
            Select::make('student_id')
                ->label(__('Student'))
                ->required()
                ->searchable()
                // Searched rather than listed: a centre's student roll runs to
                // thousands, and every one of them would otherwise be loaded
                // into the modal to fill a single dropdown.
                ->getSearchResultsUsing(fn (string $search): array => self::searchStudents($record, $search))
                ->getOptionLabelUsing(fn ($value): ?string => self::label(Student::find($value)))
                ->helperText(__('Only students who are not already in this section are listed.'))
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $student = $state ? Student::find($state) : null;

                    $set('student_balance_display', $student
                        ? number_format((float) $student->balanceFloat, 2)
                        : null);
                })
                ->rules(self::enrolmentRules($record)),

            TextInput::make('student_balance_display')
                ->label(__('Student Wallet Balance'))
                ->prefix('₪')
                ->disabled()
                ->dehydrated(false)
                ->placeholder('—'),

            DatePicker::make('enrolled_at')
                ->label(__('Section Enrollment Date'))
                ->native(false)
                ->default(now())
                ->required()
                ->helperText(__('The day the student actually joined this section. Lessons held before it are not counted or charged — set it back when entering older registrations.')),

            Select::make('exemption_type_id')
                ->label(__('Exemption Type'))
                ->options(fn (): array => ExemptionType::query()
                    ->where('is_active', true)
                    ->get()
                    ->mapWithKeys(fn (ExemptionType $type): array => [
                        $type->id => $type->getTranslation('name', app()->getLocale(), false),
                    ])
                    ->all())
                ->searchable()
                ->preload()
                ->placeholder(__('No exemption'))
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    if (! $state) {
                        return;
                    }

                    $due = (float) ($get('amount_due') ?? 0);
                    $discount = ExemptionType::find($state)?->computeDiscount($due) ?? 0.0;

                    if ($discount > 0) {
                        $set('exemption_amount', $discount);
                        $set('amount_paid', max(0, $due - $discount));
                    }
                }),

            TextInput::make('amount_due')
                ->label(fn (): string => $record->feeLabel())
                ->numeric()
                ->prefix('₪')
                ->required()
                ->minValue(0)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                    'amount_paid',
                    max(0, (float) ($get('amount_due') ?? 0) - (float) ($get('exemption_amount') ?? 0)),
                ))
                ->helperText(fn (): ?string => $record->isPerSessionBilled()
                    ? __('Billed one cycle at a time: :summary', ['summary' => $record->feeSummary()])
                    : null),

            TextInput::make('exemption_amount')
                ->label(__('Exemption / Discount'))
                ->numeric()
                ->prefix('₪')
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => blank($state) ? 0 : $state)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                    'amount_paid',
                    max(0, (float) ($get('amount_due') ?? 0) - (float) ($get('exemption_amount') ?? 0)),
                )),

            TextInput::make('amount_paid')
                ->label(__('Amount To Be Paid'))
                ->numeric()
                ->prefix('₪')
                ->required()
                ->minValue(0)
                ->helperText(__('Will be auto-deducted from the student wallet on save.')),

            Textarea::make('note')
                ->label(__('Note'))
                ->rows(2)
                ->columnSpanFull(),

            ...EnrollmentPayment::schema(fn (Get $get): float => (float) ($get('amount_paid') ?? 0)),
        ];
    }

    /**
     * The three gates every other enrolment screen runs, pointed the other way
     * round.
     *
     * `SectionEnrolmentRules` was written for a form where the operator picks a
     * section, so each rule reads the section out of the field it is attached
     * to. Here the section is the page and the field holds a student, so the
     * section id is handed over directly and the field's value is read as the
     * student it actually is.
     *
     * @return list<Closure(): Closure>
     */
    protected static function enrolmentRules(Section $record): array
    {
        $sectionId = (int) $record->getKey();

        $againstThisSection = fn (Closure $rule): Closure => function (string $attribute, $value, Closure $fail) use ($rule, $sectionId): void {
            $rule($attribute, $sectionId, $fail);
        };

        // Each rule is wrapped in a closure that *returns* it: Filament
        // evaluates whatever sits in the rules array with its own parameter
        // injection, and a bare `fn ($attribute, $value, $fail)` is read as a
        // request for three container bindings it cannot resolve.
        return [
            fn (): Closure => $againstThisSection(SectionEnrolmentRules::hasTrainer()),
            fn (): Closure => $againstThisSection(SectionEnrolmentRules::hasRoom()),
            fn (): Closure => function (string $attribute, $value, Closure $fail) use ($sectionId): void {
                $clash = SectionEnrolmentRules::noScheduleClash($value ? (int) $value : null);

                $clash($attribute, $sectionId, $fail);
            },
        ];
    }

    /**
     * Students who could still join: everyone the centre has, less the ones
     * already sitting in this section. A student who withdrew is offered again
     * — putting them back is a legitimate thing to want to do.
     *
     * @return array<int, string>
     */
    protected static function searchStudents(Section $record, string $search): array
    {
        $taken = Registration::query()
            ->where('section_id', $record->getKey())
            ->stillEnrolled()
            ->pluck('student_id');

        return Student::query()
            ->whereNotIn('id', $taken)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('student_number', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%"))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => self::label($student)])
            ->all();
    }

    /** "Full Name (STU-123456)" — the student as the desk asks for them. */
    protected static function label(?Student $student): ?string
    {
        if (! $student) {
            return null;
        }

        $name = $student->getTranslation('name', app()->getLocale(), false) ?: '#'.$student->id;

        return $student->student_number
            ? $name.' ('.$student->student_number.')'
            : $name;
    }

    /** @param  array<string, mixed>  $data */
    protected static function enroll(Section $record, array $data): void
    {
        // The payment has to reach the wallet before the charge does, or the
        // student ends up owing what they just handed over.
        $data = EnrollmentPayment::collect($data, (int) $data['student_id']);

        $registration = Registration::create([
            'student_id' => $data['student_id'],
            'section_id' => $record->getKey(),
            'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
            'exemption_type_id' => $data['exemption_type_id'] ?? null,
            'amount_due' => $data['amount_due'] ?? 0,
            'exemption_amount' => $data['exemption_amount'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? 0,
            'note' => $data['note'] ?? null,
        ]);

        Notification::make()
            ->success()
            ->title(__('Registration created'))
            ->body($registration->studentLabel())
            ->send();
    }
}
