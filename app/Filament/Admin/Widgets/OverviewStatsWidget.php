<?php

namespace App\Filament\Admin\Widgets;

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

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $monthRevenue = Registration::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_paid');

        return [
            Stat::make(__('Students'), Student::count())
                ->description(__('Total students'))
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make(__('Trainers'), Trainer::count())
                ->description(__('Total trainers'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make(__('Active Sections'), Section::query()
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '>=', now()->toDateString())
                ->count())
                ->description(__('Currently running or upcoming'))
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color('info'),

            Stat::make(__('Open Complaints'), Complaint::where('status', Complaint::STATUS_OPEN)->count())
                ->description(__('Need attention'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make(__('Revenue this Month'), number_format((float) $monthRevenue, 2).' ₪')
                ->description(__('Sum of amount_paid in :month', ['month' => now()->translatedFormat('F')]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
