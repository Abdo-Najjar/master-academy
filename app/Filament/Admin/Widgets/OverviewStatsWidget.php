<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Trainer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $weekRevenue = Registration::query()
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount_paid');

        $monthRevenue = Registration::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_paid');

        $activeStudents = Student::where('status', 'active')->count();
        $newStudents = Student::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $withdrawnStudents = Student::where('status', 'withdrawn')->count();
        $suspendedStudents = Student::where('status', 'suspended')->count();

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

        $todaySessions = Attendance::whereDate('date', today())
            ->distinct('section_id')
            ->count('section_id');

        return [
            Stat::make(__('Active Students'), $activeStudents)
                ->description(__('New this month') . ': ' . $newStudents)
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make(__('Withdrawn'), $withdrawnStudents)
                ->description(__('Suspended') . ': ' . $suspendedStudents)
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('danger'),

            Stat::make(__('Weekly Revenue'), number_format((float) $weekRevenue, 0) . ' ₪')
                ->description(__('Monthly') . ': ' . number_format((float) $monthRevenue, 0) . ' ₪')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('Due Payments'), $dueStudents)
                ->description(__('Overdue') . ': ' . $overdueStudents)
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($dueStudents > 0 ? 'warning' : 'success'),

            Stat::make(__('Sessions Today'), $todaySessions)
                ->description(__('Sections with attendance recorded'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make(__('Open Complaints'), Complaint::where('status', Complaint::STATUS_OPEN)->count())
                ->description(__('Need attention'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
