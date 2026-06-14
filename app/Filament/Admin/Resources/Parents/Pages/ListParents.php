<?php

namespace App\Filament\Admin\Resources\Parents\Pages;

use App\Filament\Admin\Resources\Parents\ParentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParents extends ListRecords
{
    protected static string $resource = ParentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
