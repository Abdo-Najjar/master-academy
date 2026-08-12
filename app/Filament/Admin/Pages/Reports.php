<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Trainer;
use App\Services\FinancialDueService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

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

    public function getTopTrainersProperty()
    {
        [$from, $to] = $this->range();

        // These are hand-written joins, so Eloquent's soft-delete scope does not
        // reach the joined `registrations` table — deleted registrations have to
        // be excluded explicitly or they keep counting toward trainer revenue.
        $liveRegistrations = fn ($q) => $q
            ->join('registrations', 'sections.id', '=', 'registrations.section_id')
            ->whereNull('registrations.deleted_at')
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

        // Raw query builder: no model, therefore no soft-delete scope at all.
        // Every joined table needs its own `deleted_at` guard.
        return \DB::table('registrations')
            ->join('sections', 'registrations.section_id', '=', 'sections.id')
            ->leftJoin('subjects', function ($join): void {
                $join->on('sections.subject_id', '=', 'subjects.id')
                    ->whereNull('subjects.deleted_at');
            })
            ->whereNull('registrations.deleted_at')
            ->whereNull('sections.deleted_at')
            ->whereBetween('registrations.created_at', [$from, $to])
            ->selectRaw('COALESCE(subjects.name, ?) as subject_name, COUNT(*) as total, SUM(registrations.funded_amount) as revenue', [__('Not set')])
            ->groupBy('subject_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    /** Students with overdue/due payments + WhatsApp links. */
    public function getDueStudentsProperty()
    {
        return Registration::query()
            ->reportable()
            ->whereIn('financial_status', ['overdue', 'due'])
            ->with(['student', 'section.subject'])
            ->orderByRaw("CASE financial_status WHEN 'overdue' THEN 0 WHEN 'due' THEN 1 ELSE 2 END")
            ->limit(50)
            ->get();
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
