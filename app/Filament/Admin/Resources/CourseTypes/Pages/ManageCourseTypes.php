<?php

namespace App\Filament\Admin\Resources\CourseTypes\Pages;

use App\Filament\Admin\Resources\CourseTypes\CourseTypeResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCourseTypes extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = CourseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
