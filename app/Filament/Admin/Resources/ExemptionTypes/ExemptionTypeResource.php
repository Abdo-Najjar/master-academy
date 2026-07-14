<?php

namespace App\Filament\Admin\Resources\ExemptionTypes;

use App\Filament\Admin\Resources\ExemptionTypes\Pages\ManageExemptionTypes;
use App\Filament\Support\DeletionGuard;
use App\Models\ExemptionType;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExemptionTypeResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = ExemptionType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Exemption Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Exemption Types');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('exemption_type.index');
    }

    public function defineGates(): array
    {
        return [
            'exemption_type.index' => __('View'),
            'exemption_type.create' => __('Create'),
            'exemption_type.update' => __('Update'),
            'exemption_type.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        \App\Filament\Support\TranslatableInput::make('name', __('Name')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                        Select::make('discount_type')
                            ->label(__('Preset Discount Type'))
                            ->options([
                                'fixed' => __('Fixed Amount'),
                                'percentage' => __('Percentage of Fee'),
                            ])
                            ->placeholder(__('No preset (enter amount manually at registration)'))
                            ->live(),
                        TextInput::make('discount_value')
                            ->label(__('Discount Value'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => filled($get('discount_type')))
                            ->required(fn (Get $get): bool => filled($get('discount_type')))
                            ->suffix(fn (Get $get): string => $get('discount_type') === 'percentage' ? '%' : '₪')
                            ->helperText(__('Auto-filled as the exemption amount when this type is selected at registration.')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('discount_value')
                    ->label(__('Preset Discount'))
                    ->state(fn (ExemptionType $record): string => match ($record->discount_type) {
                        'percentage' => rtrim(rtrim(number_format((float) $record->discount_value, 2), '0'), '.').'%',
                        'fixed' => number_format((float) $record->discount_value, 2).' ₪',
                        default => '—',
                    }),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (ExemptionType $record) => static::guardDeletion($record)),
                    ForceDeleteAction::make()
                        ->before(fn (ExemptionType $record) => static::guardDeletion($record)),
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

    protected static function guardDeletion(ExemptionType $record): void
    {
        DeletionGuard::ensureUnused($record, [
            'registrations' => __('Registrations'),
        ]);
    }

    /**
     * @param  Collection<int, ExemptionType>  $records
     */
    protected static function guardDeletionForMany(Collection $records): void
    {
        DeletionGuard::ensureUnusedForMany($records, [
            'registrations' => __('Registrations'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExemptionTypes::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
