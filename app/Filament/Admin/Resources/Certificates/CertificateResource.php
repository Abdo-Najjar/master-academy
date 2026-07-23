<?php

namespace App\Filament\Admin\Resources\Certificates;

use App\Filament\Admin\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Admin\Resources\Certificates\Tables\CertificatesTable;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'serial_number';

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Certificate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Certificates');
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->can('certificate.index') ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return CertificatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['student', 'section.subject', 'template', 'issuedBy']);
    }
}
