<?php

namespace App\Filament\Admin\Resources\Sections\Tables;

use App\Models\Section;
use App\Support\TrainerRate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The three money columns are sums over the section's live
            // registrations, aggregated in the query rather than per row —
            // "what is every section owed, and what has it collected?" is a
            // question about the whole list, and answering it row by row would
            // be one query per section.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum(['registrations as expected_amount' => fn (Builder $q) => $q->reportable()], 'amount_paid')
                ->withSum(['registrations as collected_amount' => fn (Builder $q) => $q->reportable()], 'funded_amount'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('subject.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color(fn ($record) => $record->subject?->color ? Color::hex($record->subject->color) : 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch.name')->label(__('Branch'))->badge()->placeholder('—')->searchable()->sortable(),
                TextColumn::make('trainer.name')->label(__('Trainer'))->searchable()->sortable(),
                TextColumn::make('start_date')->label(__('Start'))->date()->sortable(),
                TextColumn::make('end_date')->label(__('End'))->date()->sortable(),
                // Reads the fee the section is actually billed on, so a
                // per-session section no longer shows up as ₪0.
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->state(fn (Section $record): string => $record->feeSummary())
                    ->description(fn (Section $record): ?string => $record->isPerSessionBilled() ? $record->feeLabel() : null)
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderByRaw("CASE WHEN fee_type = 'per_sessions' THEN cycle_fee ELSE price END {$direction}")),
                TextColumn::make('trainer_rate')->label(__('Trainer Rate'))->formatStateUsing(fn ($state) => TrainerRate::label($state))->sortable(),
                TextColumn::make('min_capacity')
                    ->label(__('Minimum Capacity'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('capacity')->label(__('Maximum Capacity'))->placeholder('—')->sortable(),
                TextColumn::make('training_hours')->label(__('Training Hours'))->sortable(),
                // Seats currently taken, which is what "enrolled" has to mean
                // next to the capacity column — students who withdrew gave
                // their seat back.
                TextColumn::make('registrations_count')
                    ->counts(['registrations' => fn (Builder $query) => $query->stillEnrolled()])
                    ->label(__('Enrolled')),
                // What each section is owed, what came in, and the gap. Summed
                // over `amount_paid` (the net charge after exemptions) rather
                // than `amount_due` (the list price), so a discounted student
                // is not counted at money nobody ever asked them for.
                TextColumn::make('expected_amount')
                    ->label(__('Expected'))
                    ->money('ILS', decimalPlaces: 0)
                    ->default(0)
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('registration.index') ?? false),
                TextColumn::make('collected_amount')
                    ->label(__('Collected'))
                    ->money('ILS', decimalPlaces: 0)
                    ->default(0)
                    ->color('success')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('registration.index') ?? false),
                TextColumn::make('outstanding_amount')
                    ->label(__('Outstanding'))
                    // Both sums are already on the row; the difference costs
                    // nothing more than subtracting them.
                    ->state(fn (Section $record): float => max(
                        0,
                        round((float) $record->expected_amount - (float) $record->collected_amount, 2),
                    ))
                    ->money('ILS', decimalPlaces: 0)
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state > 0.009 ? 'danger' : 'gray')
                    // Computed in PHP, so there is no column to sort on — sort
                    // on the expression the two aggregates make instead.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("(COALESCE(expected_amount, 0) - COALESCE(collected_amount, 0)) {$direction}"))
                    ->visible(fn (): bool => auth()->user()?->can('registration.index') ?? false),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __(ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'info',
                        'active' => 'success',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('subject_id')
                    ->label(__('Course'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('trainer_id')
                    ->label(__('Trainer'))
                    ->relationship('trainer', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
