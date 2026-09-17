<?php

namespace App\Filament\Admin\Resources\Students\Actions;

use App\Filament\Support\EnrollmentPayment;
use App\Filament\Support\SectionEnrolmentRules;
use App\Models\ExemptionType;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Quick Enroll for one student, from the student's own page.
 *
 * The registrations list further down the page can already do this, but adding
 * a course is something you decide while looking at the student, not after
 * scrolling past their wallet — so it belongs in the actions menu too. Same
 * rules and the same "take the money now" block as every other enrolment
 * screen, so nothing can be slipped past by using this door instead.
 */
class EnrollInSectionAction
{
    public static function make(): Action
    {
        return Action::make('enrollInSection')
            ->label(__('Enroll in a Section'))
            ->icon('heroicon-o-academic-cap')
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('registration.create') ?? false)
            ->modalHeading(__('Enroll in a Section'))
            ->modalSubmitActionLabel(__('Enroll'))
            ->schema(fn (Student $record): array => self::schema($record))
            ->action(fn (Student $record, array $data) => self::enroll($record, $data));
    }

    /** @return list<mixed> */
    protected static function schema(Student $record): array
    {
        return [
            Select::make('section_id')
                ->label(__('Section'))
                ->options(fn (): array => self::availableSections($record))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $section = $state ? Section::find($state) : null;

                    if (! $section) {
                        return;
                    }

                    // Per-session sections are billed one cycle at a time, so
                    // the first charge is the cycle fee, not a course price.
                    $set('amount_due', $section->displayFee());
                    $set('amount_paid', $section->displayFee());
                })
                ->rules([
                    fn () => SectionEnrolmentRules::hasTrainer(),
                    fn () => SectionEnrolmentRules::noScheduleClash($record->getKey()),
                    fn () => SectionEnrolmentRules::hasRoom(),
                ]),

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
                    ->mapWithKeys(fn (ExemptionType $t): array => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false),
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
                ->label(__('Amount Due'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix('₪')
                ->required()
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                    'amount_paid',
                    max(0, (float) ($get('amount_due') ?? 0) - (float) ($get('exemption_amount') ?? 0)),
                )),

            TextInput::make('exemption_amount')
                ->label(__('Exemption / Discount'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix('₪')
                ->dehydrateStateUsing(fn ($state) => blank($state) ? 0 : $state)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Get $get, Set $set) => $set(
                    'amount_paid',
                    max(0, (float) ($get('amount_due') ?? 0) - (float) ($get('exemption_amount') ?? 0)),
                )),

            TextInput::make('amount_paid')
                ->label(__('Amount To Be Paid'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix('₪')
                ->required()
                ->helperText(__('Will be auto-deducted from the student wallet on save.')),

            ...EnrollmentPayment::schema(fn (Get $get): float => (float) ($get('amount_paid') ?? 0)),
        ];
    }

    /**
     * Sections the student could actually join: the ones they are not already
     * registered in. A section with no trainer is still listed, so the rule can
     * explain why rather than the option silently going missing.
     *
     * @return array<int, string>
     */
    protected static function availableSections(Student $record): array
    {
        $taken = Registration::query()
            ->where('student_id', $record->getKey())
            ->stillEnrolled()
            ->pluck('section_id');

        return Section::query()
            ->with('subject')
            ->whereNotIn('id', $taken)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Section $section): array => [
                $section->id => $section->name
                    .($section->subject ? ' — '.$section->subject->getTranslation('name', app()->getLocale(), false) : ''),
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    protected static function enroll(Student $record, array $data): void
    {
        // The payment has to reach the wallet before the charge does, or the
        // student ends up owing what they just handed over.
        $data = EnrollmentPayment::collect($data, $record->getKey());

        $registration = Registration::create([
            'student_id' => $record->getKey(),
            'section_id' => $data['section_id'],
            'enrolled_at' => $data['enrolled_at'] ?? now()->toDateString(),
            'exemption_type_id' => $data['exemption_type_id'] ?? null,
            'amount_due' => $data['amount_due'] ?? 0,
            'exemption_amount' => $data['exemption_amount'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? 0,
        ]);

        Notification::make()
            ->success()
            ->title(__('Registration created'))
            ->body($registration->sectionLabel())
            ->send();
    }
}
