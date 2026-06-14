<?php

namespace App\Filament\Admin\Resources\Parents;

use App\Filament\Admin\Resources\Parents\Pages\CreateParent;
use App\Filament\Admin\Resources\Parents\Pages\EditParent;
use App\Filament\Admin\Resources\Parents\Pages\ListParents;
use App\Filament\Admin\Resources\Parents\Pages\ViewParent;
use App\Filament\Admin\Resources\Parents\RelationManagers\StudentsRelationManager;
use App\Filament\Admin\Resources\Parents\Schemas\ParentForm;
use App\Filament\Admin\Resources\Parents\Schemas\ParentInfolist;
use App\Filament\Admin\Resources\Parents\Tables\ParentsTable;
use App\Models\ParentGuardian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ParentResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = ParentGuardian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Parent');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Parents');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('parent.index');
    }

    public function defineGates(): array
    {
        return [
            'parent.index' => __('View'),
            'parent.create' => __('Create'),
            'parent.update' => __('Update'),
            'parent.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ParentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ParentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParents::route('/'),
            'create' => CreateParent::route('/create'),
            'view' => ViewParent::route('/{record}'),
            'edit' => EditParent::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
