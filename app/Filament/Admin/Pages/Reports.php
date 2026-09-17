<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Expense;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\RoomBooking;
use App\Models\RoomBookingPayment;
use App\Models\Student;
use App\Models\Trainer;
use App\Services\FinancialDueService;
use App\Support\BranchContext;
use BackedEnum;
use Bavix\Wallet\Models\Transaction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class Reports extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.admin.pages.reports';

    protected static ?int $navigationSort = 1;

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
            'trainer_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label(__('From'))
                            ->native(false)
                            ->required()
                            ->live(),
                        DatePicker::make('date_to')
                            ->label(__('To'))
                            ->native(false)
                            ->required()
                            ->live(),
                        Select::make('trainer_id')
                            ->label(__('Trainer (optional)'))
                            ->options(Trainer::query()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ])
            ->statePath('filters');
    }

    protected function range(): array
    {
        $from = Carbon::parse($this->filters['date_from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->filters['date_to'] ?? now()->endOfMonth())->endOfDay();

        return [$from, $to];
    }

    public function getStatsProperty(): array
    {
        [$from, $to] = $this->range();
        $trainerId = $this->filters['trainer_id'] ?? null;

        $registrations = Registration::query()
            ->reportable()
            ->whereBetween('created_at', [$from, $to])
            ->when($trainerId, fn ($q) => $q->whereHas('section', fn ($s) => $s->where('trainer_id', $trainerId)));

        $registrationsCount = (clone $registrations)->count();
        $revenue = (clone $registrations)->sum('funded_amount');
        $exemptions = (clone $registrations)->sum('exemption_amount');
        $trainerShare = (clone $registrations)->sum('trainer_credited_amount');
        $outstanding = FinancialDueService::outstandingAmount(clone $registrations);

        $newStudents = Student::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $attendance = Attendance::query()
            ->reportable()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($trainerId, fn ($q) => $q->whereHas('section', fn ($s) => $s->where('trainer_id', $trainerId)))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalAttendance = array_sum($attendance);
        $present = (int) ($attendance['present'] ?? 0) + (int) ($attendance['late'] ?? 0);
        $rate = $totalAttendance > 0 ? round(($present / $totalAttendance) * 100, 1) : 0;

        return [
            'registrations' => $registrationsCount,
            'new_students' => $newStudents,
            'revenue' => (float) $revenue,
            'exemptions' => (float) $exemptions,
            'trainer_share' => (float) $trainerShare,
            'net_revenue' => (float) $revenue - (float) $trainerShare,
            'outstanding' => $outstanding,
            'attendance_rate' => $rate,
            'attendance_total' => $totalAttendance,
            'attendance_breakdown' => [
                'present' => (int) ($attendance['present'] ?? 0),
                'absent' => (int) ($attendance['absent'] ?? 0),
                'late' => (int) ($attendance['late'] ?? 0),
                'excused' => (int) ($attendance['excused'] ?? 0),
            ],
        ];
    }

    /**
     * A trainer filter is a question about teaching. Rent, bills and halls let
     * out to outsiders belong to the centre rather than to any one trainer, so
     * the money-out panels step aside instead of showing centre-wide totals
     * under a trainer's name.
     */
    public function hasTrainerFilter(): bool
    {
        return filled($this->filters['trainer_id'] ?? null);
    }

    /**
     * Money out in the selected window, by the day it was actually spent.
     *
     * @return array{total: float, count: int}
     */
    public function getExpenseStatsProperty(): array
    {
        [$from, $to] = $this->range();

        $expenses = Expense::query()->reportable()->spentBetween($from, $to);

        return [
            'total' => (float) (clone $expenses)->sum('amount'),
            'count' => (clone $expenses)->count(),
        ];
    }

    /**
     * Expenses in the window grouped by kind, biggest first — the answer to
     * "where did the money go", which a single total cannot give.
     *
     * @return Collection<int, array{name: string, total: float, count: int}>
     */
    public function getExpenseBreakdownProperty()
    {
        [$from, $to] = $this->range();
        $locale = app()->getLocale();

        return Expense::query()
            ->reportable()
            ->spentBetween($from, $to)
            ->with('expenseType')
            ->get()
            ->groupBy('expense_type_id')
            ->map(fn ($rows): array => [
                'name' => $rows->first()->expenseType?->getTranslation('name', $locale, false) ?? __('Not set'),
                'total' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Room bookings: what was banked in the window, and what the bookings
     * running through it are still owed.
     *
     * Collected is counted by the day each instalment arrived — the same basis
     * as every other cash figure on this page. Outstanding is a snapshot of the
     * bookings themselves, floored per booking so an overpaid hall cannot
     * cancel out an unpaid one.
     *
     * @return array{count: int, contracted: float, collected: float, outstanding: float}
     */
    public function getBookingStatsProperty(): array
    {
        [$from, $to] = $this->range();

        $collected = RoomBookingPayment::query()
            ->reportable()
            ->paidBetween($from, $to)
            ->sum('amount');

        $bookings = RoomBooking::query()
            ->reportable()
            ->active()
            ->overlapping($from, $to)
            // Loaded as an aggregate so the outstanding sum below stays one
            // query rather than one per booking.
            ->withSum('payments as paid_total', 'amount')
            ->get();

        return [
            'count' => $bookings->count(),
            'contracted' => (float) $bookings->sum('price'),
            'collected' => (float) $collected,
            'outstanding' => round($bookings->sum(fn (RoomBooking $booking): float => $booking->remainingAmount()), 2),
        ];
    }

    /**
     * Money that actually came through the desk in the window, split by how it
     * was handed over — the line a cashier reconciles against the till.
     *
     * Deliberately not the same number as "revenue": revenue is what the
     * courses opened in the window are worth, while this is cash banked in it,
     * whichever course or hall it was for and whenever that was agreed. Both
     * streams are here because both hit the same drawer — a student's deposit
     * and a hall's instalment are the same shekel to whoever counts it.
     *
     * @return array{total: float, count: int, by_type: Collection<int, array{name: string, total: float, count: int}>}
     */
    public function getCollectionsProperty(): array
    {
        [$from, $to] = $this->range();
        $names = PaymentType::all()->mapWithKeys(
            fn (PaymentType $type): array => [$type->id => $type->getTranslation('name', app()->getLocale(), false)]
        );
        $unset = __('Not set');

        $rows = collect();

        // Student money lives on the wallet, where the payment method was
        // recorded in the movement's meta rather than in a column.
        BranchContext::scopeWalletTransactions(
            Transaction::query()
                ->where('type', 'deposit')
                ->where('payable_type', Student::class)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$from, $to])
        )
            ->get()
            ->each(function (Transaction $transaction) use ($rows, $names, $unset): void {
                $rows->push([
                    'name' => $names[$transaction->meta['payment_type_id'] ?? null] ?? $unset,
                    'amount' => abs((float) $transaction->amountFloat),
                ]);
            });

        RoomBookingPayment::query()
            ->reportable()
            ->paidBetween($from, $to)
            ->get()
            ->each(function (RoomBookingPayment $payment) use ($rows, $names, $unset): void {
                $rows->push([
                    'name' => $names[$payment->payment_type_id] ?? $unset,
                    'amount' => (float) $payment->amount,
                ]);
            });

        return [
            'total' => round($rows->sum('amount'), 2),
            'count' => $rows->count(),
            'by_type' => $rows
                ->groupBy('name')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'total' => round($group->sum('amount'), 2),
                    'count' => $group->count(),
                ])
                ->sortByDesc('total')
                ->values(),
        ];
    }

    /**
     * What the centre actually kept: student money minus the trainers' share,
     * plus what the halls brought in, minus everything that was paid out.
     */
    public function getNetResultProperty(): float
    {
        return round(
            $this->stats['net_revenue'] + $this->bookingStats['collected'] - $this->expenseStats['total'],
            2
        );
    }

    public function getTopTrainersProperty()
    {
        [$from, $to] = $this->range();

        // These are hand-written joins, so Eloquent's soft-delete scope does not
        // reach the joined `registrations` table — deleted registrations have to
        // be excluded explicitly or they keep counting toward trainer revenue.
        $branchId = BranchContext::currentBranchId();

        $liveRegistrations = fn ($q) => $q
            ->join('registrations', 'sections.id', '=', 'registrations.section_id')
            ->whereNull('registrations.deleted_at')
            // Hand-written join again: the section's own branch scope does not
            // reach a table it was joined into, so it is spelled out.
            ->when($branchId, fn ($query, int $id) => $query->where('sections.branch_id', $id))
            ->whereBetween('registrations.created_at', [$from, $to]);

        return Trainer::query()
            ->withCount(['sections as registrations_count' => $liveRegistrations])
            ->withSum(['sections as revenue' => $liveRegistrations], 'registrations.funded_amount')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
    }

    /**
     * Top subjects by enrollments + revenue in the selected window.
     */
    public function getSubjectBreakdownProperty()
    {
        [$from, $to] = $this->range();

        // Raw query builder: no model, therefore no soft-delete scope at all
        // *and no branch scope either*. Every joined table needs its own
        // `deleted_at` guard, and the branch has to be applied by hand — an
        // employee reading their own branch's report must not have the other
        // site's courses quietly summed into it.
        return \DB::table('registrations')
            ->join('sections', 'registrations.section_id', '=', 'sections.id')
            ->leftJoin('subjects', function ($join): void {
                $join->on('sections.subject_id', '=', 'subjects.id')
                    ->whereNull('subjects.deleted_at');
            })
            ->whereNull('registrations.deleted_at')
            ->whereNull('sections.deleted_at')
            ->when(
                BranchContext::currentBranchId(),
                fn ($query, int $branchId) => $query->where('sections.branch_id', $branchId),
            )
            ->whereBetween('registrations.created_at', [$from, $to])
            ->selectRaw('COALESCE(subjects.name, ?) as subject_name, COUNT(*) as total, SUM(registrations.funded_amount) as revenue', [__('Not set')])
            ->groupBy('subject_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    /**
     * Students with overdue/due payments.
     *
     * A full table rather than a printed list: the desk works this panel row by
     * row — chasing one student, taking the money there and then — so it needs
     * search, sorting, filters and pages over every debtor, not the first fifty
     * in a fixed order.
     *
     * Deliberately not tied to the date filters above. The rest of the page
     * reports on a window; this is a snapshot of who owes money *now*, and a
     * debt does not stop existing because the reader was looking at last month.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Registration::query()
                    ->reportable()
                    ->whereIn('financial_status', ['overdue', 'due'])
                    ->with(['student', 'section.subject', 'section.trainer'])
            )
            ->heading(__('Students with Due / Overdue Payments'))
            ->description(__('Everyone still owing money right now — not limited to the selected period.'))
            ->emptyStateHeading(__('No due payments'))
            ->emptyStateDescription(__('Nobody is currently behind on a payment.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->url(fn (Registration $record): ?string => $this->studentUrl($record))
                    ->openUrlInNewTab(),
                TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.subject.name')
                    ->label(__('Subject'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('section.trainer.name')
                    ->label(__('Trainer'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('financial_status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'overdue' => __('Overdue'),
                        'due' => __('Due'),
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => $state === 'overdue' ? 'danger' : 'warning'),
                TextColumn::make('amount_paid')
                    ->label(__('Charge'))
                    ->money('ILS', decimalPlaces: 0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('funded_amount')
                    ->label(__('Collected'))
                    ->money('ILS', decimalPlaces: 0)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('remaining')
                    ->label(__('Outstanding'))
                    ->state(fn (Registration $record): float => FinancialDueService::remainingBalance($record))
                    ->money('ILS', decimalPlaces: 0)
                    ->weight('bold')
                    ->color('danger')
                    // Computed in PHP, so there is no column to sort on — sort
                    // the two figures it is the difference of instead.
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderByRaw("(amount_paid - funded_amount) {$direction}")),
                TextColumn::make('contact_phone')
                    ->label(__('Phone'))
                    ->state(fn (Registration $record): ?string => $this->contactNumber($record))
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('enrolled_at')
                    ->label(__('Section Enrollment Date'))
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('financial_status')
                    ->label(__('Status'))
                    ->options([
                        'overdue' => __('Overdue'),
                        'due' => __('Due'),
                    ]),
                SelectFilter::make('section_id')
                    ->label(__('Section'))
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('trainer')
                    ->label(__('Trainer'))
                    ->options(fn (): array => Trainer::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $trainerId) => $q->whereHas('section', fn ($s) => $s->where('trainer_id', $trainerId)),
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    CollectPaymentAction::make(),
                    Action::make('whatsapp')
                        ->label(__('WhatsApp'))
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->url(fn (Registration $record): ?string => $this->whatsAppUrl($record))
                        ->openUrlInNewTab()
                        ->visible(fn (Registration $record): bool => filled($this->whatsAppUrl($record))),
                    Action::make('viewStudent')
                        ->label(__('View Student'))
                        ->icon('heroicon-o-user')
                        ->url(fn (Registration $record): ?string => $this->studentUrl($record))
                        ->visible(fn (Registration $record): bool => filled($this->studentUrl($record))),
                ]),
            ])
            // Worst first, so the panel opens on whoever has been owing longest.
            ->defaultSort('financial_status', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->striped();
    }

    /** Where a debtor's name points, or null when the reader may not go there. */
    private function studentUrl(Registration $registration): ?string
    {
        $student = $registration->student;

        if (! $student || $student->trashed() || ! StudentResource::canView($student)) {
            return null;
        }

        return StudentResource::getUrl('view', ['record' => $student->getKey()]);
    }

    /** The number to reach this student on: WhatsApp if we hold one, else the phone. */
    private function contactNumber(Registration $registration): ?string
    {
        $student = $registration->student;

        return $student?->whatsapp_number ?: $student?->phone_number;
    }

    /** A pre-written payment reminder, or null when we hold no number to send it to. */
    private function whatsAppUrl(Registration $registration): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $this->contactNumber($registration));

        if (blank($phone)) {
            return null;
        }

        $message = __('Payment reminder for :name in :section', [
            'name' => $registration->student?->name,
            'section' => $registration->section?->name,
        ]);

        return 'https://wa.me/'.$phone.'?text='.urlencode($message);
    }

    /** Withdrawal / suspension counts in selected period. */
    public function getWithdrawalStatsProperty(): array
    {
        [$from, $to] = $this->range();

        return [
            'withdrawn' => Student::query()->where('status', 'withdrawn')
                ->whereBetween('withdrawal_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'suspended' => Student::query()->where('status', 'suspended')->count(),
            'archived' => Student::query()->where('status', 'archived')->count(),
            'certificates_issued' => Certificate::query()
                ->whereBetween('issued_at', [$from, $to])
                ->count(),
        ];
    }
}
