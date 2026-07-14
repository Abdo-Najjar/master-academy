<?php

namespace App\Filament\Admin\Resources\Complaints\Pages;

use App\Filament\Admin\Resources\Complaints\ComplaintResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
        ];
    }
}
