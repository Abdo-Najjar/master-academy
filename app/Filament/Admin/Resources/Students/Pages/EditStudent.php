<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use App\Filament\Admin\Resources\Students\Tables\StudentsTable;
use App\Filament\Support\CapturesAuditReason;
use App\Models\Student;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    use CapturesAuditReason;

    protected static string $resource = StudentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->before(fn (Student $record) => StudentsTable::guardStudentDeletion($record)),
            ForceDeleteAction::make()
                ->before(fn (Student $record) => StudentsTable::guardStudentDeletion($record)),
            RestoreAction::make(),
        ];
    }
}
