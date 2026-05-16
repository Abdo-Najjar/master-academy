<?php

namespace App\Filament\Trainer\Resources\Sections;

use App\Filament\Trainer\Resources\Sections\Pages\EditSection;
use App\Filament\Trainer\Resources\Sections\Pages\ListSections;
use App\Filament\Trainer\Resources\Sections\Pages\ViewSection;
use App\Filament\Trainer\Resources\Sections\RelationManagers\AttendancesRelationManager;
use App\Filament\Trainer\Resources\Sections\RelationManagers\RegistrationsRelationManager;
use App\Filament\Trainer\Resources\Sections\Schemas\SectionForm;
use App\Filament\Trainer\Resources\Sections\Schemas\SectionInfolist;
use App\Filament\Trainer\Resources\Sections\Tables\SectionsTable;
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

    public static function getModelLabel(): string
    {
        return __('Section');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Sections');
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

    public static function getEloquentQuery(): Builder
    {
        $trainerId = Auth::guard('trainer')->id();

        return parent::getEloquentQuery()->where('trainer_id', $trainerId);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
            AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSections::route('/'),
            'view' => ViewSection::route('/{record}'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
