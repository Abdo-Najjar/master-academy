<?php

namespace App\Filament\Admin\Resources\Assignments\Pages;

use App\Filament\Admin\Resources\Assignments\AssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssignment extends EditRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('assignment.update');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => hexa()->can('assignment.delete')),
        ];
    }
}
