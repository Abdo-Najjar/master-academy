<?php

namespace App\Filament\Admin\Resources\Certificates\Pages;

use App\Filament\Admin\Resources\Certificates\CertificateResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Resources\Pages\ListRecords;

class ListCertificates extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
        ];
    }
}
