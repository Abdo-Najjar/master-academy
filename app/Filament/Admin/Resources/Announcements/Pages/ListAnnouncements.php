<?php

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()
                ->visible(fn () => (auth()->user()?->can('announcement.create') ?? false)),
        ];
    }
}
