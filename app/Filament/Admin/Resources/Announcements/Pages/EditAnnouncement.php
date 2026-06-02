<?php

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('announcement.update');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => hexa()->can('announcement.delete')),
        ];
    }
}
