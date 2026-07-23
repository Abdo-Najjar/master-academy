<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns\Pages;

use App\Filament\Admin\Resources\WhatsappCampaigns\WhatsappCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsappCampaign extends CreateRecord
{
    protected static string $resource = WhatsappCampaignResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (auth()->user()?->can('whatsapp_campaign.create') ?? false);
    }
}
