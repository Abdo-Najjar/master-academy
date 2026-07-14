<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Pages;

use App\Filament\Admin\Resources\CertificateTemplates\CertificateTemplateResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCertificateTemplates extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = CertificateTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
