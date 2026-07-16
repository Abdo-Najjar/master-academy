<?php

namespace App\Filament\Admin\Resources\Assignments\Pages;

use App\Filament\Admin\Resources\Assignments\AssignmentResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssignments extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = AssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()->visible(fn () => hexa()->can('assignment.create')),
        ];
    }
}
