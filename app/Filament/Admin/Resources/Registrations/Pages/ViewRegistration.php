<?php

namespace App\Filament\Admin\Resources\Registrations\Pages;

use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Registrations\RegistrationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CollectPaymentAction::make(),
            EditAction::make(),
        ];
    }
}
