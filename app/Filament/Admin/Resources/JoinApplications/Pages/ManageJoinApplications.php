<?php

namespace App\Filament\Admin\Resources\JoinApplications\Pages;

use App\Filament\Admin\Resources\JoinApplications\JoinApplicationResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Resources\Pages\ManageRecords;

class ManageJoinApplications extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = JoinApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
        ];
    }
}
