<?php

namespace App\Filament\Admin\Resources\Parents\Pages;

use App\Filament\Admin\Resources\Parents\ParentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateParent extends CreateRecord
{
    protected static string $resource = ParentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
