<?php

namespace App\Filament\Admin\Resources\Governorates;

use App\Filament\Admin\Resources\Governorates\Pages\ManageGovernorates;
use App\Filament\Support\DeletionGuard;
use App\Models\Governorate;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Support\Collection;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GovernorateResource extends Resource
{
    protected static ?string $model = Governorate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Locations');
    }

    public static function getModelLabel(): string
    {
        return __('Governorate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Governorates');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('governorate.index') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        \App\Filament\Support\TranslatableInput::make('name', __('Name')),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cities_count')
                    ->counts('cities')
                    ->label(__('Cities'))
                    ->sortable(),
                TextColumn::make('created_at')->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (Governorate $record) => static::guardDeletion($record)),
                    ForceDeleteAction::make()
                        ->before(fn (Governorate $record) => static::guardDeletion($record)),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    ForceDeleteBulkAction::make()
                        ->before(fn (Collection $records) => static::guardDeletionForMany($records)),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function guardDeletion(Governorate $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'cities' => __('Cities'),
            'students' => __('Students'),
            'trainers' => __('Trainers'),
        ]);
    }

    /**
     * @param  Collection<int, Governorate>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'cities' => __('Cities'),
            'students' => __('Students'),
            'trainers' => __('Trainers'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGovernorates::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
