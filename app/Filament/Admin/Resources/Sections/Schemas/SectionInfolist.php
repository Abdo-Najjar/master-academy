<?php

namespace App\Filament\Admin\Resources\Sections\Schemas;

use App\Models\Section;
use App\Support\TrainerRate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

class SectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfoSection::make('')
                    ->schema([
                        TextEntry::make('name')->label(__('Section Name'))->columnSpanFull(),
                        TextEntry::make('subject.name')
                            ->label(__('Course'))
                            ->badge()
                            ->color(fn ($record) => $record->subject?->color ? Color::hex($record->subject->color) : 'gray')
                            ->placeholder('—'),
                        TextEntry::make('branch.name')->label(__('Branch'))->placeholder('—'),
                        TextEntry::make('trainer.name')->label(__('Trainer'))->placeholder('—'),
                        TextEntry::make('start_date')->label(__('Start Date'))->date()->placeholder('—'),
                        TextEntry::make('end_date')->label(__('End Date'))->date()->placeholder('—'),
                        TextEntry::make('price')
                            ->label(fn (Section $record): string => $record->feeLabel())
                            ->state(fn (Section $record): string => $record->feeSummary()),
                        TextEntry::make('trainer_rate')
                            ->label(__('Trainer Rate (%)'))
                            // Reads back the way it was entered: "الثلث (33.3333%)".
                            ->formatStateUsing(fn ($state) => TrainerRate::label($state) ?? '—'),
                        // How many students are actually in the section — the
                        // first thing anyone opening it wants to know, and the
                        // page never said.
                        TextEntry::make('enrolled')
                            ->label(__('Enrolled'))
                            ->state(fn (Section $record): string => $record->seatsSummary())
                            ->badge()
                            ->color(fn (Section $record): string => match (true) {
                                $record->isFull() => 'danger',
                                $record->isBelowMinimum() => 'warning',
                                default => 'success',
                            })
                            ->helperText(fn (Section $record): ?string => $record->isBelowMinimum()
                                ? __('Below the minimum of :count students.', ['count' => $record->min_capacity])
                                : null),
                        TextEntry::make('capacity')
                            ->label(__('Capacity'))
                            ->state(fn (Section $record): string => match (true) {
                                $record->min_capacity && $record->capacity => $record->min_capacity.' – '.$record->capacity,
                                (bool) $record->capacity => __('Up to :count', ['count' => $record->capacity]),
                                (bool) $record->min_capacity => __('At least :count', ['count' => $record->min_capacity]),
                                default => '—',
                            }),
                        TextEntry::make('training_hours')->label(__('Training Hours'))->numeric()->placeholder('—'),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Section $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
