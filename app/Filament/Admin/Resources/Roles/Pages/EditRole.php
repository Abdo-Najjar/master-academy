<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Support\PermissionCatalog;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->can('role.delete') ?? false),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $granted = $this->record->permissions->pluck('name')->all();

        foreach (PermissionCatalog::all() as $module => $gates) {
            $data['permissions'][$module] = array_values(array_intersect(array_keys($gates), $granted));
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissions = collect($data['permissions'] ?? [])->flatten()->unique()->values()->all();
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->permissions);
    }

    /** @var list<string> */
    protected array $permissions = [];
}
