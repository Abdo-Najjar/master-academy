<?php

namespace App\Filament\Admin\Resources\SectionSessions\Pages;

use App\Filament\Admin\Resources\SectionSessions\SectionSessionResource;
use App\Filament\Support\ExportsTableRecords;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSectionSessions extends ManageRecords
{
    use ExportsTableRecords;

    protected static string $resource = SectionSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->tableExportAction(),
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->can('section_session.create') ?? false),
        ];
    }
}
