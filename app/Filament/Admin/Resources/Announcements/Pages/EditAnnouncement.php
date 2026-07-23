<?php

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (auth()->user()?->can('announcement.update') ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => (auth()->user()?->can('announcement.delete') ?? false)),
        ];
    }
}
