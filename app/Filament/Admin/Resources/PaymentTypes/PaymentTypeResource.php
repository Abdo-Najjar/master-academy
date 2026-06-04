<?php

namespace App\Filament\Admin\Resources\PaymentTypes;

use App\Filament\Admin\Resources\PaymentTypes\Pages\ManagePaymentTypes;
use App\Models\PaymentType;
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
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentTypeResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = PaymentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Payment Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payment Types');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('payment_type.index');
    }

    public function defineGates(): array
    {
        return [
            'payment_type.index' => __('View'),
            'payment_type.create' => __('Create'),
            'payment_type.update' => __('Update'),
            'payment_type.delete' => __('Delete'),
        ];
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
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('created_at')->label(__('Created'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentTypes::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
