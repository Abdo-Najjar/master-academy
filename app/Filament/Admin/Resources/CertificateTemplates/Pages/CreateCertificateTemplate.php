<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Pages;

use App\Filament\Admin\Resources\CertificateTemplates\CertificateTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCertificateTemplate extends CreateRecord
{
    protected static string $resource = CertificateTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return CertificateTemplateResource::getUrl('design', ['record' => $this->record]);
    }
}
