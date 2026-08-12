<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Pages\QuickEnroll;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            // Students are always created through Quick Enroll: a student with
            // no section is of no use to anyone, and the plain create form left
            // them half-registered with nothing charged.
            Action::make('quickEnroll')
                ->label(__('Quick Enroll'))
                ->icon('heroicon-o-user-plus')
                ->url(QuickEnroll::getUrl())
                ->visible(fn (): bool => QuickEnroll::canAccess()),
        ];
    }
}
