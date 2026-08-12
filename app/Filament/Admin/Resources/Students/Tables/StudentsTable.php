<?php

namespace App\Filament\Admin\Resources\Students\Tables;

use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Filament\Support\DeletionGuard;
use App\Models\Student;
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
use Illuminate\Support\Collection;

class StudentsTable
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
                TextColumn::make('student_number')->label(__('Student Number'))->searchable(),
                TextColumn::make('username')->label(__('Username'))->searchable(),
                TextColumn::make('ssn')->label(__('SSN'))->searchable()->toggleable(),
                TextColumn::make('phone_number')->label(__('Phone'))->searchable(),
                TextColumn::make('school')->label(__('School'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('grade_level')->label(__('Grade Level'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parent_name')->label(__('Guardian Name'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parent_phone')->label(__('Guardian Phone'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('governorate.name')->label(__('Governorate'))->toggleable(),
                TextColumn::make('city.name')->label(__('City'))->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'withdrawn' => 'danger',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => __(ucfirst($state)))
                    ->sortable(),
                TextColumn::make('registrations_count')->counts('registrations')->label(__('Registrations')),
                TextColumn::make('balanceFloat')->label(__('Wallet Balance'))->money('ILS', decimalPlaces: 0)->getStateUsing(fn ($record) => $record->balanceFloat),
                IconColumn::make('is_active')->label(__('Active'))->boolean()->sortable(),
                TextColumn::make('dob')->label(__('Date of Birth'))->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Active'),
                        'suspended' => __('Suspended'),
                        'withdrawn' => __('Withdrawn'),
                        'archived' => __('Archived'),
                    ]),
                SelectFilter::make('governorate_id')
                    ->label(__('Governorate'))
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    WalletActions::deposit(),
                    WalletActions::withdraw(),
                    DeleteAction::make()
                        ->before(fn (Student $record) => self::guardStudentDeletion($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (Collection $records) => self::guardDeletionForMany($records)),
                    ForceDeleteBulkAction::make()
                        ->before(fn (Collection $records) => self::guardDeletionForMany($records)),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    /**
     * A student enrolled in a course carries money, attendance and grades with
     * them — deleting the row would orphan all of it, so withdraw or archive
     * them via the status field instead.
     */
    public static function guardStudentDeletion(Student $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'registrations' => __('Registrations'),
        ]);
    }

    /** @param  Collection<int, Student>  $records */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'registrations' => __('Registrations'),
        ]);
    }
}
