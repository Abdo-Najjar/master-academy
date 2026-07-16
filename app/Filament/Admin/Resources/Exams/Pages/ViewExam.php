<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\Actions\EnterGradesAction;
use App\Filament\Admin\Resources\Exams\Actions\TogglePublishGradesAction;
use App\Filament\Admin\Resources\Exams\ExamResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EnterGradesAction::make(),
            TogglePublishGradesAction::make(),
            EditAction::make(),
        ];
    }
}
