<?php

namespace App\Filament\Admin\Pages;

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Trainer;
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
use Hexters\HexaLite\HasHexaLite;

class Reports extends Page implements HasForms
{
    use HasHexaLite;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.admin.pages.reports';

    /** High sort value so it lands near the bottom of the standalone (un-grouped) items. */
    protected static ?int $navigationSort = 90;

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        // Ungrouped — appears as a standalone top-level entry in the sidebar.
        return null;
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
        return hexa()->can('reports.view');
    }

    public function defineGates(): array
    {
        return [
            'reports.view' => __('View Reports'),
        ];
    }

    public function roleName(): string
    {
        return __('Reports');
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
                    ->columns(3)
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
            ->whereBetween('created_at', [$from, $to])
            ->when($trainerId, fn ($q) => $q->whereHas('section', fn ($s) => $s->where('trainer_id', $trainerId)));

        $registrationsCount = (clone $registrations)->count();
        $revenue = (clone $registrations)->sum('amount_paid');
        $exemptions = (clone $registrations)->sum('exemption_amount');
        $trainerShare = (clone $registrations)->sum('trainer_amount');

        $newStudents = Student::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $attendance = Attendance::query()
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

        return Trainer::query()
            ->withCount(['sections as registrations_count' => function ($q) use ($from, $to) {
                $q->join('registrations', 'sections.id', '=', 'registrations.section_id')
                    ->whereBetween('registrations.created_at', [$from, $to]);
            }])
            ->withSum(['sections as revenue' => function ($q) use ($from, $to) {
                $q->join('registrations', 'sections.id', '=', 'registrations.section_id')
                    ->whereBetween('registrations.created_at', [$from, $to]);
            }], 'registrations.amount_paid')
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

        return \DB::table('registrations')
            ->join('sections', 'registrations.section_id', '=', 'sections.id')
            ->leftJoin('subjects', 'sections.subject_id', '=', 'subjects.id')
            ->whereBetween('registrations.created_at', [$from, $to])
            ->selectRaw('COALESCE(subjects.name, ?) as subject_name, COUNT(*) as total, SUM(registrations.amount_paid) as revenue', [__('Not set')])
            ->groupBy('subject_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }
}
