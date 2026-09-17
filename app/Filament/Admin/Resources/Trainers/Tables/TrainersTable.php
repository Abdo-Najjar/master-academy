<?php

namespace App\Filament\Admin\Resources\Trainers\Tables;

use App\Filament\Admin\Resources\Trainers\Actions\WalletActions;
use App\Support\BranchContext;
use App\Support\TrainerRate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('main')
                    ->label(__('Image'))
                    ->collection('main')
                    ->circular(),
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('trainer_number')->label(__('Trainer Number'))->searchable(),
                TextColumn::make('username')->label(__('Username'))->searchable(),
                TextColumn::make('ssn')->label(__('SSN'))->searchable()->toggleable(),
                TextColumn::make('email')->label(__('Email'))->searchable()->toggleable(),
                TextColumn::make('phone_number')->label(__('Phone'))->searchable(),
                // A trainer can work at several sites, so this is a list of
                // badges rather than a single branch name.
                TextColumn::make('branches.name')
                    ->label(__('Branches'))
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('default_rate')->label(__('Default Rate'))->formatStateUsing(fn ($state) => TrainerRate::label($state))->sortable(),
                TextColumn::make('subjects_count')->counts('subjects')->label(__('Courses')),
                TextColumn::make('sections_count')->counts('sections')->label(__('Sections')),
                TextColumn::make('balanceFloat')->label(__('Wallet Balance'))->money('ILS', decimalPlaces: 0)->getStateUsing(fn ($record) => $record->balanceFloat),
                IconColumn::make('is_active')->label(__('Active'))->boolean()->sortable(),
                TextColumn::make('dob')->label(__('Date of Birth'))->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Options from `selectableBranches()` rather than the
                // relationship: the branch name is translatable, so plucking it
                // straight out of the column hands back the whole JSON document
                // where a label should be.
                SelectFilter::make('branch')
                    ->label(__('Branch'))
                    ->options(fn (): array => BranchContext::selectableBranches())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $scoped, $branchId) => $scoped->whereHas(
                            'branches',
                            fn (Builder $branch) => $branch->whereKey($branchId),
                        ),
                    ))
                    ->visible(fn (): bool => ! BranchContext::isRestricted()),
                TernaryFilter::make('is_active')->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    WalletActions::deposit(),
                    WalletActions::withdraw(),
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
