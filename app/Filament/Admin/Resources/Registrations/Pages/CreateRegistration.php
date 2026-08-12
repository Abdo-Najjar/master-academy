<?php

namespace App\Filament\Admin\Resources\Registrations\Pages;

use App\Filament\Admin\Resources\Registrations\RegistrationResource;
use App\Filament\Support\EnrollmentPayment;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * Bank the payment before the registration is written — the observer reads
     * the wallet balance as it stands beforehand to decide how much of the
     * charge is actually funded.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EnrollmentPayment::collect($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
