<?php

namespace App\Filament\Admin\Resources\Branches;

use App\Filament\Admin\Resources\Branches\Pages\ManageBranches;
use App\Filament\Support\DeletionGuard;
use App\Filament\Support\TranslatableInput;
use App\Models\Branch;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Locations');
    }

    public static function getModelLabel(): string
    {
        return __('Branch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Branches');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branch.index') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TranslatableInput::make('name', __('Name')),
                        Select::make('governorate_id')
                            ->label(__('Governorate'))
                            ->relationship('governorate', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('city_id', null)),
                        Select::make('city_id')
                            ->label(__('City'))
                            ->relationship('city', 'name', fn ($query, callable $get) => $query->where('governorate_id', $get('governorate_id')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => empty($get('governorate_id'))),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make(__('Public Site'))
                    ->schema([
                        TranslatableInput::make('address', __('Address'), required: false),
                        TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),
                        Toggle::make('show_on_site')
                            ->label(__('Show on site'))
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(1)
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
                TextColumn::make('governorate.name')
                    ->label(__('Governorate'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city.name')
                    ->label(__('City'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label(__('Sections')),
                ToggleColumn::make('show_on_site')
                    ->label(__('Show on site')),
                TextColumn::make('created_at')->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('governorate_id')
                    ->label(__('Governorate'))
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city_id')
                    ->label(__('City'))
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (Branch $record) => static::guardDeletion($record)),
                    ForceDeleteAction::make()
                        ->before(fn (Branch $record) => static::guardDeletion($record)),
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

    protected static function guardDeletion(Branch $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'sections' => __('Sections'),
        ]);
    }

    /**
     * @param  Collection<int, Branch>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'sections' => __('Sections'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
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
