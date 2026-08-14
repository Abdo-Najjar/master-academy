<?php

namespace App\Filament\Admin\Resources\Registrations\Pages;

use App\Filament\Admin\Resources\Registrations\RegistrationResource;
use App\Filament\Support\CapturesAuditReason;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    use CapturesAuditReason;

    protected static string $resource = RegistrationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
