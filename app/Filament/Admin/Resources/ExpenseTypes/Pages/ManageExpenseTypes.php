<?php

namespace App\Filament\Admin\Resources\ExpenseTypes\Pages;

use App\Filament\Admin\Resources\ExpenseTypes\ExpenseTypeResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenseTypes extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = ExpenseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
