<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Registration;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DuePaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Students with Due Payments'))
            ->query(
                Registration::query()
                    ->whereNull('deleted_at')
                    ->whereIn('financial_status', ['due', 'overdue', 'warning'])
                    ->with(['student', 'section.subject'])
                    ->orderByRaw("CASE financial_status WHEN 'overdue' THEN 0 WHEN 'due' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            )
            ->emptyStateHeading(__('No due payments'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable(),
                TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->searchable(),
                TextColumn::make('section.subject.name')
                    ->label(__('Subject')),
                TextColumn::make('financial_status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'overdue' => 'danger',
                        'due' => 'warning',
                        'warning' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'overdue' => __('Overdue'),
                        'due' => __('Due'),
                        'warning' => __('Warning'),
                        default => $state,
                    }),
                TextColumn::make('amount_due')
                    ->label(__('Amount Due'))
                    ->money('ILS', decimalPlaces: 0),
                TextColumn::make('amount_paid')
                    ->label(__('Paid'))
                    ->money('ILS', decimalPlaces: 0),
            ])
            ->paginated([10, 25])
            ->defaultSort('financial_status');
    }
}
