<?php

namespace App\Filament\Admin\Resources\RoomBookings\Pages;

use App\Filament\Admin\Resources\RoomBookings\RoomBookingResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomBookings extends ListRecords
{
    use ExportsTableRecords;

    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make(),
        ];
    }
}
