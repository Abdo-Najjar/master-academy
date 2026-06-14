<?php

namespace App\Filament\Admin\Resources\Trainers\Widgets;

use App\Models\Registration;
use App\Models\Trainer;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TrainerEarningsWidget extends BaseWidget
{
    public ?Trainer $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Earnings by Section'))
            ->query(
                Registration::query()
                    ->whereNull('registrations.deleted_at')
                    ->whereHas('section', fn ($q) => $q->where('trainer_id', $this->record?->id))
                    ->with(['section.subject'])
            )
            ->columns([
                TextColumn::make('section.name')
                    ->label(__('Section'))
                    ->searchable(),
                TextColumn::make('section.subject.name')
                    ->label(__('Subject')),
                TextColumn::make('amount_paid')
                    ->label(__('Student Paid'))
                    ->money('ILS', decimalPlaces: 0)
                    ->summarize(Sum::make()->label(__('Total'))->money('ILS', decimalPlaces: 0)),
                TextColumn::make('trainer_amount')
                    ->label(__('Trainer Share'))
                    ->money('ILS', decimalPlaces: 0)
                    ->summarize(Sum::make()->label(__('Total Due to Trainer'))->money('ILS', decimalPlaces: 0)),
                TextColumn::make('financial_status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'overdue' => 'danger',
                        'due' => 'warning',
                        'warning' => 'info',
                        default => 'success',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'overdue' => __('Overdue'),
                        'due' => __('Due'),
                        'warning' => __('Warning'),
                        'ok' => __('OK'),
                        default => $state,
                    }),
            ])
            ->paginated([25, 50])
            ->defaultSort('section_id');
    }
}
