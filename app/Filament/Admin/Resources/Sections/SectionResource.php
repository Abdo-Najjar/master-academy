<?php

namespace App\Filament\Admin\Resources\Sections;

use App\Filament\Admin\Resources\Sections\Pages\CreateSection;
use App\Filament\Admin\Resources\Sections\Pages\EditSection;
use App\Filament\Admin\Resources\Sections\Pages\ListSections;
use App\Filament\Admin\Resources\Sections\Pages\ViewSection;
use App\Filament\Admin\Resources\Sections\RelationManagers\RegistrationsRelationManager;
use App\Filament\Admin\Resources\Sections\RelationManagers\TimesRelationManager;
use App\Filament\Admin\Resources\Sections\Schemas\SectionForm;
use App\Filament\Admin\Resources\Sections\Schemas\SectionInfolist;
use App\Filament\Admin\Resources\Sections\Tables\SectionsTable;
use App\Models\Section;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Section');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sections');
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('view_sections') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TimesRelationManager::class,
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSections::route('/'),
            'create' => CreateSection::route('/create'),
            'view' => ViewSection::route('/{record}'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
