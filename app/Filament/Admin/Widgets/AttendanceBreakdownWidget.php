<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Attendance;
use Filament\Widgets\ChartWidget;

class AttendanceBreakdownWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return __('Attendance breakdown — last 30 days');
    }

    protected function getData(): array
    {
        $counts = Attendance::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);

        return [
            'datasets' => [
                [
                    'label' => __('Attendance'),
                    'data' => [$present, $absent, $late, $excused],
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                        'rgb(245, 158, 11)',
                        'rgb(59, 130, 246)',
                    ],
                ],
            ],
            'labels' => [
                __('Present'),
                __('Absent'),
                __('Late'),
                __('Excused'),
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
