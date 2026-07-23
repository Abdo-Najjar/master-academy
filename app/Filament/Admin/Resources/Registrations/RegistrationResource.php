<?php

namespace App\Filament\Admin\Resources\Registrations;

use App\Filament\Admin\Resources\Registrations\Pages\CreateRegistration;
use App\Filament\Admin\Resources\Registrations\Pages\EditRegistration;
use App\Filament\Admin\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Admin\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Admin\Resources\Registrations\Schemas\RegistrationForm;
use App\Filament\Admin\Resources\Registrations\Schemas\RegistrationInfolist;
use App\Filament\Admin\Resources\Registrations\Tables\RegistrationsTable;
use App\Models\Registration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Registration');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Registrations');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('registration.index') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return RegistrationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistrationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'create' => CreateRegistration::route('/create'),
            'view' => ViewRegistration::route('/{record}'),
            'edit' => EditRegistration::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
