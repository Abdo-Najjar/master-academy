<?php

namespace App\Filament\Admin\Resources\ExemptionTypes\Pages;

use App\Filament\Admin\Resources\ExemptionTypes\ExemptionTypeResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExemptionTypes extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = ExemptionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
