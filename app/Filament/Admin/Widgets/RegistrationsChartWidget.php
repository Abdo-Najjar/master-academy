<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Registration;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class RegistrationsChartWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return __('Registrations — last 30 days');
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $rows = Registration::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->toDateString();
            $labels[] = $date->translatedFormat('d M');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Registrations'),
                    'data' => $values,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
