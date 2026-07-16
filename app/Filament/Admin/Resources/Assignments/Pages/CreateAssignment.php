<?php

namespace App\Filament\Admin\Resources\Assignments\Pages;

use App\Filament\Admin\Resources\Assignments\AssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssignment extends CreateRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('assignment.create');
    }
}
