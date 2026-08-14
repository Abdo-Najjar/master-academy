<?php

namespace App\Filament\Admin\Resources\Sections\Pages;

use App\Filament\Admin\Resources\Sections\SectionResource;
use App\Filament\Support\CapturesAuditReason;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSection extends EditRecord
{
    use CapturesAuditReason;

    protected static string $resource = SectionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
