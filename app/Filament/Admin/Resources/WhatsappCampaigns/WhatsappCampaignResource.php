<?php

namespace App\Filament\Admin\Resources\WhatsappCampaigns;

use App\Filament\Admin\Resources\WhatsappCampaigns\Pages\CreateWhatsappCampaign;
use App\Filament\Admin\Resources\WhatsappCampaigns\Pages\ListWhatsappCampaigns;
use App\Filament\Admin\Resources\WhatsappCampaigns\Pages\ViewWhatsappCampaign;
use App\Filament\Admin\Resources\WhatsappCampaigns\RelationManagers\RecipientsRelationManager;
use App\Filament\Admin\Resources\WhatsappCampaigns\Schemas\WhatsappCampaignForm;
use App\Filament\Admin\Resources\WhatsappCampaigns\Schemas\WhatsappCampaignInfolist;
use App\Filament\Admin\Resources\WhatsappCampaigns\Tables\WhatsappCampaignsTable;
use App\Models\WhatsappCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;

class WhatsappCampaignResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = WhatsappCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }

    public static function getModelLabel(): string
    {
        return __('WhatsApp Campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('WhatsApp Campaigns');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('whatsapp_campaign.index');
    }

    public function defineGates(): array
    {
        return [
            'whatsapp_campaign.index' => __('View'),
            'whatsapp_campaign.create' => __('Create'),
            'whatsapp_campaign.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappCampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WhatsappCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappCampaigns::route('/'),
            'create' => CreateWhatsappCampaign::route('/create'),
            'view' => ViewWhatsappCampaign::route('/{record}'),
        ];
    }
}
