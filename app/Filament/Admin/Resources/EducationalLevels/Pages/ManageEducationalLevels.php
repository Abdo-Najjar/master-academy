<?php

namespace App\Filament\Admin\Resources\EducationalLevels\Pages;

use App\Filament\Admin\Resources\EducationalLevels\EducationalLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEducationalLevels extends ManageRecords
{
    protected static string $resource = EducationalLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
