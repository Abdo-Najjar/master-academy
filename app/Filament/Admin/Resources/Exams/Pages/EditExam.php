<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\ExamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return hexa()->can('exam.update');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => hexa()->can('exam.delete')),
        ];
    }
}
