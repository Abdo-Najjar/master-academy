<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Support\PermissionCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $labels = PermissionCatalog::moduleLabels();

        $sections = collect(PermissionCatalog::all())->map(
            fn (array $gates, string $module) => Section::make($labels[$module] ?? $module)
                ->collapsed(false)
                ->schema([
                    CheckboxList::make("permissions.{$module}")
                        ->hiddenLabel()
                        ->columns(2)
                        ->bulkToggleable()
                        ->options($gates),
                ])
        )->values();

        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('Role Name'))
                    ->maxLength(100)
                    ->required(),
                ...$sections,
            ]);
    }
}
