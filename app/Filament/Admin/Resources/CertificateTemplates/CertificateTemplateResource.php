<?php

namespace App\Filament\Admin\Resources\CertificateTemplates;

use App\Filament\Admin\Resources\CertificateTemplates\Pages\CreateCertificateTemplate;
use App\Filament\Admin\Resources\CertificateTemplates\Pages\EditCertificateTemplate;
use App\Filament\Admin\Resources\CertificateTemplates\Pages\ListCertificateTemplates;
use App\Filament\Admin\Resources\CertificateTemplates\Pages\DesignCertificateTemplate;
use App\Filament\Admin\Resources\CertificateTemplates\Schemas\CertificateTemplateForm;
use App\Filament\Admin\Resources\CertificateTemplates\Tables\CertificateTemplatesTable;
use App\Models\CertificateTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CertificateTemplateResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = CertificateTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getModelLabel(): string
    {
        return __('Certificate Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Certificate Templates');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('certificate_template.index');
    }

    public function defineGates(): array
    {
        return [
            'certificate_template.index' => __('View'),
            'certificate_template.create' => __('Create'),
            'certificate_template.update' => __('Update'),
            'certificate_template.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CertificateTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CertificateTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificateTemplates::route('/'),
            'create' => CreateCertificateTemplate::route('/create'),
            'edit' => EditCertificateTemplate::route('/{record}/edit'),
            'design' => DesignCertificateTemplate::route('/{record}/design'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
