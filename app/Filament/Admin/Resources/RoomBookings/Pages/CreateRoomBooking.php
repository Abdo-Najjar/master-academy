<?php

namespace App\Filament\Admin\Resources\RoomBookings\Pages;

use App\Filament\Admin\Resources\RoomBookings\RoomBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoomBooking extends CreateRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
