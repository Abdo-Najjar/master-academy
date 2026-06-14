<?php

namespace App\Filament\Admin\Resources\Parents\Pages;

use App\Filament\Admin\Resources\Parents\ParentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewParent extends ViewRecord
{
    protected static string $resource = ParentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
