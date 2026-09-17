<?php

namespace App\Filament\Admin\Resources\RoomBookings\Pages;

use App\Filament\Admin\Resources\RoomBookings\Actions\CollectBookingPaymentAction;
use App\Filament\Admin\Resources\RoomBookings\RoomBookingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoomBooking extends ViewRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CollectBookingPaymentAction::make(),
            EditAction::make(),
        ];
    }
}
