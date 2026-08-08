<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Registration;
use App\Models\Student;
use App\Services\FinancialDueService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    /**
     * Every stat this widget can render, keyed by the permission that unlocks it.
     * A user only ever sees the figures for modules they can already open from
     * the navigation, so a "students only" operator never sees revenue or
     * complaint counts.
     *
     * @var array<string, string>
     */
    protected const GATES = [
        'student.index' => 'studentStats',
        'registration.index' => 'financialStats',
        'attendance.index' => 'attendanceStats',
        'complaint.index' => 'complaintStats',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (array_keys(self::GATES) as $gate) {
            if ($user->can($gate)) {
                return true;
            }
        }

        return false;
    }

    protected function getStats(): array
    {
        $stats = [];

        foreach (self::GATES as $gate => $method) {
            if (auth()->user()?->can($gate)) {
                $stats = [...$stats, ...$this->{$method}()];
            }
        }

        return $stats;
    }

    /** @return list<Stat> */
    protected function studentStats(): array
    {
        $activeStudents = Student::where('status', 'active')->count();
        $newStudents = Student::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $withdrawnStudents = Student::where('status', 'withdrawn')->count();
        $suspendedStudents = Student::where('status', 'suspended')->count();

        return [
            Stat::make(__('Active Students'), $activeStudents)
                ->description(__('New this month').': '.$newStudents)
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make(__('Withdrawn'), $withdrawnStudents)
                ->description(__('Suspended').': '.$suspendedStudents)
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('danger'),
        ];
    }

    /** @return list<Stat> */
    protected function financialStats(): array
    {
        $weekRevenue = Registration::query()
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('funded_amount');

        $monthRevenue = Registration::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('funded_amount');

        $outstanding = FinancialDueService::outstandingAmount();

        $dueStudents = Registration::query()
            ->whereNull('deleted_at')
            ->whereIn('financial_status', ['due', 'overdue'])
            ->distinct('student_id')
            ->count('student_id');

        $overdueStudents = Registration::query()
            ->whereNull('deleted_at')
            ->where('financial_status', 'overdue')
            ->distinct('student_id')
            ->count('student_id');

        return [
            Stat::make(__('Weekly Revenue'), number_format((float) $weekRevenue, 0).' ₪')
                ->description(__('Monthly').': '.number_format((float) $monthRevenue, 0).' ₪')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('Outstanding from Students'), number_format($outstanding, 0).' ₪')
                ->description(__('Charged but not yet collected'))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($outstanding > 0 ? 'danger' : 'success'),

            Stat::make(__('Due Payments'), $dueStudents)
                ->description(__('Overdue').': '.$overdueStudents)
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($dueStudents > 0 ? 'warning' : 'success'),
        ];
    }

    /** @return list<Stat> */
    protected function attendanceStats(): array
    {
        $todaySessions = Attendance::whereDate('date', today())
            ->distinct('section_id')
            ->count('section_id');

        return [
            Stat::make(__('Sessions Today'), $todaySessions)
                ->description(__('Sections with attendance recorded'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }

    /** @return list<Stat> */
    protected function complaintStats(): array
    {
        return [
            Stat::make(__('Open Complaints'), Complaint::where('status', Complaint::STATUS_OPEN)->count())
                ->description(__('Need attention'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
