<?php

namespace App\Filament\Admin\Resources\Governorates\Pages;

use App\Filament\Admin\Resources\Governorates\GovernorateResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGovernorates extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = GovernorateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
