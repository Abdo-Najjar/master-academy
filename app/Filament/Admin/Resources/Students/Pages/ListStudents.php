<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\Importers\StudentImporter;
use App\Filament\Admin\Resources\Students\StudentResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(StudentImporter::class)
                ->label(__('Import Students'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
