<?php

namespace App\Filament\Admin\Resources\StudentGroups\Pages;

use App\Filament\Admin\Resources\StudentGroups\StudentGroupResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStudentGroups extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = StudentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()->visible(fn () => (auth()->user()?->can('student_group.create') ?? false)),
        ];
    }
}
