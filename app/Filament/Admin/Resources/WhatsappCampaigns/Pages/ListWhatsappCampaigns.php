<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Pages;

use App\Filament\Admin\Resources\WhatsappCampaigns\WhatsappCampaignResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappCampaigns extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = WhatsappCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()->visible(fn () => hexa()->can('whatsapp_campaign.create')),
        ];
    }
}
