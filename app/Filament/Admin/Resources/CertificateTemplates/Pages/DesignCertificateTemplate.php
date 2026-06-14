<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Pages;

use App\Filament\Admin\Resources\CertificateTemplates\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Http\Request;

class DesignCertificateTemplate extends Page
{
    protected static string $resource = CertificateTemplateResource::class;

    protected string $view = 'filament.admin.pages.certificate-designer';

    public CertificateTemplate $record;

    public function mount(CertificateTemplate $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return __('Design Template') . ': ' . $this->record->name;
    }

    public function saveDesign(array $fieldsConfig, int $canvasWidth, int $canvasHeight): void
    {
        $this->record->update([
            'fields_config' => $fieldsConfig,
            'canvas_width' => $canvasWidth,
            'canvas_height' => $canvasHeight,
        ]);

        Notification::make()
            ->success()
            ->title(__('Template saved successfully'))
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back to Templates'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CertificateTemplateResource::getUrl('index')),
        ];
    }
}
