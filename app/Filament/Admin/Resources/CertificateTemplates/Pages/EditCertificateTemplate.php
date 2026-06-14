<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Pages;

use App\Filament\Admin\Resources\CertificateTemplates\CertificateTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCertificateTemplate extends EditRecord
{
    protected static string $resource = CertificateTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('design')
                ->label(__('Design'))
                ->icon('heroicon-o-paint-brush')
                ->color('success')
                ->url(fn () => CertificateTemplateResource::getUrl('design', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
